<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Controller;

/**
 * TabActionRouting — `action=` request routing for any HRM tab controller.
 *
 * The shared AbstractTabController routes mutations from POST submit-button
 * names (`delete_<pk>`, `<pk>_submit`). That is the right mechanism for the
 * forms it renders, but it leaves no URL-addressable entry point: a tab cannot
 * be driven by `hrm.php?page=payroll&action=delete&id=7`. hrm.php needs one
 * uniform vocabulary across the whole application, so this trait supplies it.
 *
 *   action=save    (POST)  create when no pk is set, otherwise update
 *   action=delete  (POST)  delete the record named by `id`
 *   action=edit    (GET)   redirect back with the pk set, pre-loading the form
 *
 * It delegates straight to the AbstractTabController data-access contract
 * (createRecord / updateRecord / deleteRecord), so every write still lands in
 * the *Service / Repository layer and no SQL appears in a controller.
 *
 * `action=delete` is POST-only on purpose. A GET-addressable delete is a
 * cross-site request forgery: a third-party page could issue
 * `<img src="...hrm.php?page=payroll&action=delete&id=7">` and the record would
 * go. Keeping it POST means the summary table's own Delete buttons (which are
 * POST submits) remain the only reachable path.
 *
 * PHP 7.3 compatible — no typed properties, no PHP 8+ syntax.
 *
 * @package ksf_FA_HRM
 * @since   1.1.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §11 (host page = thin router)
 * @BABOK Related: FR-HRM-001 (app shell), FR-HRM-001-002 (payroll)
 */
trait TabActionRouting
{
    /**
     * @var bool Set by a tab that cannot complete the requested mutation, so
     *           redirectAfterPost() reports the problem in place instead of
     *           PRG-redirecting away from the message.
     */
    protected $skipRedirect = false;

    /**
     * @var bool Set when an action was refused. The tab then re-renders without
     *           re-running POST routing, so the error is shown next to a usable
     *           filter / list / form rather than on an otherwise blank page.
     */
    protected $actionRefused = false;

    /**
     * Handle an `action=` request. Returns true when the action was consumed.
     *
     * @param string $action Action verb (save / delete / edit)
     *
     * @return bool
     *
     * @since 1.1.0
     */
    public function runAction(string $action): bool
    {
        $id = isset($_GET['id']) && $_GET['id'] !== ''
            ? (string) $_GET['id']
            : (string) $this->context->getRecordId();

        switch ($action) {
            case 'save':
                if (!$this->isPost()) {
                    $this->refuseAction($this->localise('Save must be submitted as a POST.'));
                    return true;
                }
                $data = $this->collectFormValues();
                if ($id !== '') {
                    $this->updateRecord($id, $data);
                    $this->finishAction($id, 'updated');
                } else {
                    $this->finishAction((string) $this->createRecord($data), 'created');
                }
                return true;

            case 'delete':
                if (!$this->isPost()) {
                    $this->refuseAction($this->localise('Delete must be submitted as a POST.'));
                    return true;
                }
                if ($id === '') {
                    $this->refuseAction($this->localise('No record was selected for deletion.'));
                    return true;
                }
                $this->deleteRecord($id);
                $this->finishAction('', 'deleted');
                return true;

            case 'edit':
                if ($id === '') {
                    return false;
                }
                $this->hrmRedirect($this->hrmActionUrl([$this->getPkField() => $id]));
                return true;
        }

        return false;
    }

    /**
     * Complete a mutation: PRG on success, re-render on refusal.
     *
     * A service that rejects the payload (a validation error, a balance that
     * cannot be released) sets skipRedirect via actionError(). Routing that
     * through redirectAfterPost() would silently do nothing, leaving the user
     * on a blank half-rendered page, so the tab is re-rendered instead — with
     * the error visible and the rejected values still in the form.
     *
     * @param string $pk     Affected record pk
     * @param string $status 'created'|'updated'|'deleted'
     *
     * @return void
     *
     * @since 1.1.0
     */
    protected function finishAction(string $pk, string $status): void
    {
        if ($this->skipRedirect) {
            $this->actionRefused = true;
            $this->renderAfterAction();
            return;
        }
        $this->redirectAfterPost($pk, $status);
    }

    /**
     * Redirect after a successful action (PRG).
     *
     * Overrides AbstractTabController::redirectAfterPost() only to honour
     * skipRedirect, so a refused mutation keeps the user on the tab with the
     * error visible instead of bouncing them away from the message.
     *
     * @param string $pk     Affected record pk ('' for delete/cancel)
     * @param string $status 'created'|'updated'|'deleted'|'cancelled'
     *
     * @return void
     *
     * @since 1.1.0
     */
    protected function redirectAfterPost(string $pk, string $status): void
    {
        if ($this->skipRedirect) {
            return;
        }

        if ($pk !== '') {
            $this->notify($this->localise('Record saved'));
        }

        // Built from PHP_SELF + $_GET rather than echoed from REQUEST_URI:
        // FA html-escapes REQUEST_URI somewhere on the POST path, which turns
        // `?a=1&b=2` into `?a=1&amp;b=2` in the Location header — a broken
        // target that silently drops the rest of the query string.
        $url = $this->hrmActionUrl([]);
        if ($url !== '') {
            $this->hrmRedirect($url);
        }
    }

    /**
     * Build a URL on this controller's own script.
     *
     * Carries the active tab plus every current GET parameter, minus the ones
     * that describe the request being answered: `action` (re-triggering it on
     * the redirected GET would replay the mutation), `id` (the row is gone or
     * already loaded into the form) and `view` (the deprecated alias of `tab`;
     * it is never propagated, `tab` is re-derived on arrival).
     *
     * `page` is deliberately left in the query: it is the pager position, and a
     * user who saved a record while on page 3 should come back to page 3.
     *
     * @param array<string, mixed> $params Extra parameters to set
     *
     * @return string
     *
     * @since 1.1.0
     */
    protected function hrmActionUrl(array $params): string
    {
        $self = isset($_SERVER['PHP_SELF']) ? (string) $_SERVER['PHP_SELF'] : '';
        $view = (string) ($this->options['view'] ?? '');

        $query = $_GET;
        unset($query['action'], $query['id'], $query['view']);

        if ($view !== '') {
            $query['tab'] = $view;
        }
        foreach ($params as $name => $value) {
            $query[$name] = $value;
        }

        return $query === [] ? $self : $self . '?' . http_build_query($query);
    }

    /**
     * Issue a Location header and stop.
     *
     * @param string $url Absolute or root-relative target
     *
     * @return void
     *
     * @since 1.1.0
     */
    protected function hrmRedirect(string $url): void
    {
        if ($url === '') {
            return;
        }
        header('Location: ' . $url);
        exit;
    }

    /**
     * @return bool Whether the current request is a POST.
     *
     * @since 1.1.0
     */
    protected function isPost(): bool
    {
        return isset($_SERVER['REQUEST_METHOD'])
            && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST';
    }

    /**
     * Report an action problem in place, without redirecting.
     *
     * @param string $message
     *
     * @return void
     *
     * @since 1.1.0
     */
    protected function actionError(string $message): void
    {
        $this->skipRedirect = true;
        if (function_exists('display_error')) {
            display_error($message);
        }
    }

    /**
     * Report a refusal and re-render the tab beneath the error.
     *
     * Refusing and then dumping the user on an otherwise empty page is a dead
     * end: they can read the message but cannot correct it. Re-rendering keeps
     * the filter, the list and the entry form on screen.
     *
     * @param string $message
     *
     * @return void
     *
     * @since 1.1.0
     */
    protected function refuseAction(string $message): void
    {
        $this->actionError($message);
        $this->actionRefused = true;
        $this->renderAfterAction();
    }

    /**
     * Render the tab after a refused action. Default re-runs run().
     *
     * @return void
     *
     * @since 1.1.0
     */
    protected function renderAfterAction(): void
    {
        $this->run();
    }

    /**
     * Preserve the active tab on pager links.
     *
     * hrm.php normalises the resolved tab into $_GET['tab'] before dispatch, and
     * MasterSummaryTable::pagerUrl() re-reads every $_GET key named in the
     * table's `preserve_params` option when building its links. So pager links
     * read `?page=<n>&tab=<key>`: the pager keeps its page number and the tab
     * survives alongside it.
     *
     * `page` is pagination only and `tab` is the tab selector — the two no
     * longer share a parameter, so there is nothing to disambiguate.
     *
     * @return string[]
     *
     * @since 1.1.0
     */
    protected function preserveParams(): array
    {
        return ['tab'];
    }
}