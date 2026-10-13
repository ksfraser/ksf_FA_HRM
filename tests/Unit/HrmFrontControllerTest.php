<?php

/**
 * Locks the behaviour ksf_FA_HRM/hrm.php used to hand-roll, so extracting the
 * preamble into AbstractFrontController cannot silently change a URL.
 *
 * @BABOK Related: FR-HRM-001 (app shell front controller)
 */

namespace Tests\Unit {

    use ksfraser\FrontAccounting\Common\App\AbstractAppShell;
    use ksfraser\FrontAccounting\Common\App\AbstractFrontController;
    use ksfraser\FrontAccounting\Common\App\FrontControllerRoutingException;
    use ksfraser\FrontAccounting\Common\App\FrontControllerSecurityException;
    use ksfraser\FrontAccounting\Common\App\TabRegistration;
    use ksfraser\FrontAccounting\HRM\App\HrmAppShell;
    use ksfraser\FrontAccounting\HRM\App\HrmFrontController;
    use PHPUnit\Framework\TestCase;

    /**
     * A shell with one controller-backed tab, so a full run() can be exercised
     * without any real tab controller reaching for a database.
     */
    class SingleTabAppShell extends AbstractAppShell
    {
        public function __construct()
        {
            parent::__construct('hrm', 'hrm.php', 'tab', 'employees');
            $this->repoint('employees', 'ThirdPartyTabController', 'SA_HRM_EMPLOYEE', 0);
        }

        /**
         * Register a controller-backed tab.
         *
         * @param string $key      Tab key
         * @param string $class    Controller class
         * @param string $security Security area, '' for none
         * @param int    $priority Registration priority
         *
         * @return void
         */
        protected function repoint(string $key, string $class, string $security = '', int $priority = 0): void
        {
            $this->registerTabFromArray([
                'key'              => $key,
                'label'            => 'Employees',
                'security'         => $security,
                'controller_class' => $class,
                'priority'         => $priority,
                'order'            => 0,
                'page_file'        => null,
                'options'          => ['view' => $key],
            ]);
        }

        /**
         * Mirror HrmAppShell's injection of the tab key.
         *
         * @param TabRegistration $tab Tab
         *
         * @return object|null
         */
        protected function createController(TabRegistration $tab)
        {
            $class = $tab->getControllerClass();
            if ($class === null || !class_exists($class)) {
                return null;
            }

            return new $class(null, ['view' => $tab->getKey()]);
        }
    }

    /**
     * A shell whose contributed tab carries no security area of its own — the
     * third-party case the constructor's required fallback exists for.
     */
    class AreaLessTabAppShell extends SingleTabAppShell
    {
        public function __construct()
        {
            parent::__construct();
            $this->repoint('contrib', 'ThirdPartyTabController', '', 20);
        }
    }

    /**
     * A shell whose tab controller can handle action verbs.
     */
    class ActionHandlingTabAppShell extends SingleTabAppShell
    {
        public function __construct()
        {
            parent::__construct();
            $this->repoint('employees', 'ActionHandlingTabController', 'SA_HRM_EMPLOYEE', 0);
        }
    }

    /**
     * A shell that registers nothing — the only state in which the front
     * controller is obliged to throw rather than render blank.
     *
     * The app id is deliberately NOT `hrm`: the test's hook_invoke_all() double
     * answers hrm_register_tabs with a contributed tab, and a shell that
     * accepted it would no longer be empty.
     */
    class EmptyAppShell extends AbstractAppShell
    {
        public function __construct()
        {
            parent::__construct('emptytest', 'hrm.php', 'tab', 'employees');
        }
    }

    class HrmFrontControllerTest extends TestCase
    {
        /** @var string[] Tab keys the shell registers, in menu order. */
        private const TABS = [
            'employees', 'departments', 'positions', 'grades', 'roles', 'teams',
            'payroll', 'benefits', 'leave', 'leave_types', 'recruitment', 'reports',
        ];

        /** @var string[] Security areas the access checker was called with. */
        private $checked = [];

        protected function setUp(): void
        {
            $this->checked = [];
            $GLOBALS['__hrm_tab_hook_calls'] = 0;
            $GLOBALS['__hrm_tab_dispatch'] = 0;
            $_GET = [];
            $_REQUEST = [];
        }

        protected function tearDown(): void
        {
            $_GET = [];
            $_REQUEST = [];
        }

        /**
         * Build a controller with FA's page functions absent (so
         * openPage()/closePage() no-op) and a recording access checker.
         *
         * @param AbstractAppShell|null $shell Shell to serve (default: real HrmAppShell)
         * @return HrmFrontController
         */
        private function controller($shell = null): HrmFrontController
        {
            $recorder = function (string $area): void {
                $this->checked[] = $area;
            };

            return new HrmFrontController(
                $shell ?? new HrmAppShell(),
                'SA_ksf_FA_HRMVIEW',
                '',
                ['load_ui_includes' => false, 'security_checker' => $recorder]
            );
        }

        /**
         * Run a controller with the rendered menu buffered away, so the test
         * output stays readable.
         *
         * @param HrmFrontController $controller Controller to run
         * @return string Captured output
         */
        private function runQuietly(HrmFrontController $controller): string
        {
            ob_start();
            try {
                $controller->run();
            } finally {
                $captured = (string) ob_get_clean();
            }

            return $captured;
        }

        // ─── Tab resolution: the URLs that had to keep working ──────────

        /**
         * @dataProvider tabProvider
         *
         * @param string $tab Tab key
         */
        public function testEveryRegisteredTabResolves(string $tab): void
        {
            $this->assertSame($tab, $this->controller()->resolveTabKey(['tab' => $tab]));
        }

        /**
         * @return array<string, array{0: string}>
         */
        public static function tabProvider(): array
        {
            $cases = [];
            foreach (self::TABS as $tab) {
                $cases[$tab] = [$tab];
            }

            return $cases;
        }

        public function testAllTwelveTabsAreRegisteredInMenuOrder(): void
        {
            $keys = [];
            foreach ((new HrmAppShell())->getTabs() as $tab) {
                $keys[] = $tab->getKey();
            }

            $this->assertSame(self::TABS, $keys);
        }

        /**
         * `page` is the pager position and must never be read as a tab
         * selector — the collision the rename to `tab` exists to remove. An
         * explicit `tab` still wins.
         */
        public function testPageIsNeverATabSelector(): void
        {
            $controller = $this->controller();

            $this->assertSame('employees', $controller->resolveTabKey(['page' => 'leave']));
            $this->assertSame('leave', $controller->resolveTabKey(['tab' => 'leave', 'page' => '3']));
        }

        public function testPagePositionIsPaginationAndClampedToOne(): void
        {
            $controller = $this->controller();

            $this->assertSame(3, $controller->resolvePagePosition(['page' => '3']));
            $this->assertSame(1, $controller->resolvePagePosition([]));
            $this->assertSame(1, $controller->resolvePagePosition(['page' => '-5']));
            $this->assertSame(1, $controller->resolvePagePosition(['page' => 'abc']));
        }

        /**
         * index.php minted `?view=` bookmarks long before hrm.php existed, so
         * the alias must still resolve and normalise onto the canonical
         * parameter — while the pager position survives verbatim.
         */
        public function testViewAliasResolvesAndNormalisesOntoTab(): void
        {
            $controller = $this->controller();

            $this->assertSame('leave', $controller->resolveTabKey(['view' => 'leave']));

            $_GET = ['view' => 'leave', 'page' => '3'];
            $controller->normaliseRequest('leave');

            $this->assertSame('leave', $_GET['tab']);
            $this->assertArrayNotHasKey('view', $_GET);
            $this->assertSame('3', $_GET['page']);
        }

        public function testCanonicalTabBeatsTheDeprecatedAlias(): void
        {
            $controller = $this->controller();

            $this->assertSame('payroll', $controller->resolveTabKey(['tab' => 'payroll', 'view' => 'leave']));
        }

        /**
         * A stale bookmark lands on a working tab rather than a blank page.
         */
        public function testUnknownTabFallsBackToTheDefaultView(): void
        {
            $controller = $this->controller();

            $this->assertSame('employees', $controller->resolveTabKey(['tab' => 'no-such-tab']));
        }

        public function testSecurityAreaIsTheTabsOwnAreaNotTheFallback(): void
        {
            $controller = $this->controller();
            $_GET = ['tab' => 'leave'];

            $this->assertSame('SA_HRM_LEAVE', $controller->getSecurityArea());
        }

        /**
         * A tab that registers without an area of its own is checked against
         * the constructor's fallback — which is why that argument is required
         * and may not be empty. Without it there would be a "no area, no check"
         * state to reach.
         */
        public function testATabWithoutItsOwnAreaIsCheckedAgainstTheFallback(): void
        {
            $controller = $this->controller(new AreaLessTabAppShell());
            $_GET = ['tab' => 'contrib'];

            $this->assertSame('SA_ksf_FA_HRMVIEW', $controller->getSecurityArea());

            $this->runQuietly($controller);

            $this->assertSame(['SA_ksf_FA_HRMVIEW'], $this->checked);
        }

        // ─── The register hook ─────────────────────────────────────────

        public function testRegisterHookNameIsDerivedFromTheAppId(): void
        {
            $this->assertSame('hrm_register_tabs', (new HrmAppShell())->getRegisterHook());
        }

        /**
         * A tab contributed by another module through `hrm_register_tabs` must
         * be booted, resolved, checked against its OWN area and dispatched —
         * not lost the way it would be if only the pre-boot tabs were consulted.
         */
        public function testThirdPartyTabIsBootedResolvedCheckedAndDispatched(): void
        {
            $controller = $this->controller();
            $_GET = ['tab' => 'recruit'];

            $this->runQuietly($controller);

            $this->assertGreaterThan(0, $GLOBALS['__hrm_tab_hook_calls'], 'hrm_register_tabs did not fire');
            $this->assertSame('recruit', $controller->getResolvedTab());
            $this->assertSame(['SA_ksf_FA_RECRUITVIEW'], $this->checked);
            $this->assertSame('recruit', $_GET['tab']);
            $this->assertSame(1, $GLOBALS['__hrm_tab_dispatch']);
        }

        // ─── The deliberate throw ───────────────────────────────────────

        /**
         * The one intended behaviour change: where hrm_resolve_view() silently
         * fell back, a shell with no tabs at all now raises rather than
         * rendering an empty shell.
         */
        public function testAShellWithNoTabsThrowsRatherThanRenderingBlank(): void
        {
            $controller = $this->controller(new EmptyAppShell());

            $this->expectException(FrontControllerRoutingException::class);
            $controller->run();
        }

        /**
         * ...and the real shell is nowhere near that state: every tab key, and
         * any key at all, resolves to a non-empty registration. That is why the
         * guard is a test rather than a try/catch in hrm.php.
         */
        public function testTheRealShellCanNeverTripTheThrow(): void
        {
            $shell = new HrmAppShell();

            $this->assertNotSame('', $shell->resolveView('employees')->getKey());
            $this->assertNotSame('', $shell->resolveView('no-such-tab')->getKey());
            foreach (self::TABS as $tab) {
                $this->assertSame($tab, $shell->resolveView($tab)->getKey());
            }
        }

        // ─── Actions ───────────────────────────────────────────────────

        public function testActionVerbIsReadFromRequest(): void
        {
            $controller = $this->controller();

            $this->assertSame('', $controller->resolveAction([]));
            $this->assertSame('save', $controller->resolveAction(['action' => 'save']));
            $this->assertSame('', $controller->resolveAction(['action' => ['save']]));
        }

        /**
         * A verb the controller declines (runAction() returns anything other
         * than true) falls through to a normal render, so a mistyped URL shows
         * the tab and not a blank body.
         */
        public function testUnhandledActionVerbFallsThroughToTheTab(): void
        {
            $controller = $this->controller(new ActionHandlingTabAppShell());
            $_REQUEST = ['action' => 'nonsense'];

            $this->runQuietly($controller);

            $this->assertSame('employees', $controller->getResolvedTab());
            $this->assertSame(1, $GLOBALS['__hrm_tab_dispatch']);
            $this->assertSame(['SA_HRM_EMPLOYEE'], $this->checked);
        }

        /**
         * Enforcement runs once, ahead of the action path, so `?action=` cannot
         * route around the check the read path would have made. This is the
         * step the old hand-rolled hrm_dispatch_action() had to remember.
         */
        public function testTheActionPathIsEnforcedToo(): void
        {
            $controller = $this->controller(new ActionHandlingTabAppShell());
            $_REQUEST = ['action' => 'save'];

            $this->runQuietly($controller);

            $this->assertSame(['SA_HRM_EMPLOYEE'], $this->checked);
            $this->assertSame(0, $GLOBALS['__hrm_tab_dispatch'], 'a handled verb must not also render the tab');
        }

        /**
         * A verb with nowhere to go — the tab is not controller-backed, or its
         * controller has no runAction() — says so rather than rendering blank.
         */
        public function testAVerbWithNowhereToGoRendersTheUnavailableMessage(): void
        {
            $controller = $this->controller(new SingleTabAppShell());
            $_REQUEST = ['action' => 'save'];

            $output = $this->runQuietly($controller);

            $this->assertStringContainsString('This action is not available for the selected tab.', $output);
            $this->assertSame(0, $GLOBALS['__hrm_tab_dispatch']);
            $this->assertSame(['SA_HRM_EMPLOYEE'], $this->checked, 'still enforced before the refusal');
        }

        // ─── Page framing ───────────────────────────────────────────────

        public function testPageTitleIsHrm(): void
        {
            $controller = new class (new HrmAppShell(), 'SA_ksf_FA_HRMVIEW', '') extends HrmFrontController {
                public function title(): string
                {
                    return $this->getPageTitle();
                }
            };

            $this->assertSame('HRM', $controller->title());
        }

        /**
         * The date picker belongs to payroll and leave only — the two tabs with
         * date_row() controls — and needs a user preference to be on at all.
         * With no FA session there is no preference, so none of them gets JS.
         */
        public function testDatePickerJavaScriptNeedsAnFaUserPreference(): void
        {
            $controller = new class (new HrmAppShell(), 'SA_ksf_FA_HRMVIEW', '') extends HrmFrontController {
                public function jsFor(string $tabKey): string
                {
                    $shell = new HrmAppShell();

                    return $this->getPageJs($shell->getTab($tabKey));
                }
            };

            $this->assertSame('', $controller->jsFor('payroll'));
            $this->assertSame('', $controller->jsFor('leave'));
            $this->assertSame('', $controller->jsFor('reports'));
        }

        /**
         * run() and enforceSecurity() are final: a subclass cannot replace the
         * dispatch sequence or skip the access check.
         */
        public function testRunAndEnforceSecurityAreFinal(): void
        {
            $run = new \ReflectionMethod(AbstractFrontController::class, 'run');
            $enforce = new \ReflectionMethod(AbstractFrontController::class, 'enforceSecurity');

            $this->assertTrue($run->isFinal());
            $this->assertTrue($enforce->isFinal());
            $this->assertTrue($enforce->isProtected());
        }

        /**
         * An empty fallback area is refused at construction: there is no
         * "no area configured, no check performed" state to exist.
         */
        public function testConstructorRefusesAnEmptyFallbackArea(): void
        {
            $this->expectException(FrontControllerSecurityException::class);

            new HrmFrontController(new HrmAppShell(), '   ');
        }
    }
}

namespace {

    use ksfraser\FrontAccounting\Common\App\TabRegistration;

    if (!function_exists('hook_invoke_all')) {
        /**
         * Test double for FA's hook dispatcher. Answers hrm_register_tabs with
         * one third-party tab carrying its own security area, and counts the
         * calls so a test can assert the hook actually fired.
         *
         * @param string     $hook Hook name
         * @param array|null $data Hook payload, by reference
         *
         * @return void
         */
        function hook_invoke_all($hook, &$data = null)
        {
            if ($hook !== 'hrm_register_tabs') {
                return null;
            }

            $GLOBALS['__hrm_tab_hook_calls'] = ($GLOBALS['__hrm_tab_hook_calls'] ?? 0) + 1;

            if (!isset($data['tabs']) || !is_array($data['tabs'])) {
                $data['tabs'] = [];
            }
            $data['tabs'][] = new TabRegistration(
                'recruit',
                'Recruitment',
                'SA_ksf_FA_RECRUITVIEW',
                'ThirdPartyTabController'
            );

            return null;
        }
    }

    if (!class_exists('ThirdPartyTabController', false)) {
        /**
         * The controller a third-party module would register. It has no
         * runAction(), so an action verb has nowhere to go.
         */
        class ThirdPartyTabController
        {
            /**
             * @param mixed        $context Unused (null outside FA)
             * @param array<mixed> $options Unused
             */
            public function __construct($context = null, array $options = [])
            {
            }

            /**
             * @return void
             */
            public function run(): void
            {
                $GLOBALS['__hrm_tab_dispatch'] = ($GLOBALS['__hrm_tab_dispatch'] ?? 0) + 1;
            }
        }
    }

    if (!class_exists('ActionHandlingTabController', false)) {
        /**
         * A tab controller that can handle action verbs: it claims `save` and
         * declines everything else, which must fall through to a normal render.
         */
        class ActionHandlingTabController
        {
            /**
             * @param mixed        $context Unused (null outside FA)
             * @param array<mixed> $options Unused
             */
            public function __construct($context = null, array $options = [])
            {
            }

            /**
             * @param string $action Action verb
             *
             * @return bool True when the verb was handled
             */
            public function runAction($action): bool
            {
                return $action === 'save';
            }

            /**
             * @return void
             */
            public function run(): void
            {
                $GLOBALS['__hrm_tab_dispatch'] = ($GLOBALS['__hrm_tab_dispatch'] ?? 0) + 1;
            }
        }
    }
}
