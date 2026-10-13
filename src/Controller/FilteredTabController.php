<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Controller;

use ksfraser\FrontAccounting\Common\App\AbstractTabController;
use Ksfraser\Frontaccounting\HTML\FormFooter;
use Ksfraser\Frontaccounting\HTML\MasterSummaryTable;

/**
 * FilteredTabController — HRM's filter / summary-table / data-entry-form tab.
 *
 * The shared AbstractAppShell::AbstractTabController already owns the page flow
 * (POST routing, form open/close, MasterSummaryTable summary, FieldForm entry
 * form, PRG redirect) but it has no slot for a filter bar. Every HRM tab that
 * is not a plain catalogue therefore extends this class instead, which inserts
 * one between the opening <form> and the summary table — the layout the
 * standalone pages/*.php scripts used to render by hand:
 *
 *   1. Filter bar   — controls + a Filter submit, all inside the one form.
 *   2. Summary      — AbstractTabController::renderSummaryTable().
 *   3. Entry form   — AbstractTabController::renderEntryForm(), re-rendered
 *                     here with FA's own ui_input.inc row helpers rather than
 *                     the Bootstrap markup FieldForm emits, so the four tabs
 *                     consolidated out of pages/*.php keep the exact look they
 *                     had as standalone FA pages.
 *
 * Mutation routing. hrm.php reads `action=` from the request and hands it to
 * runAction() rather than letting each tab invent its own entry point. The
 * vocabulary is uniform and small:
 *
 *   action=save    (POST)  create or update, from <pk>_submit / the form footer
 *   action=delete  (POST)  delete the record named by `id`
 *   action=edit    (GET)   redirect back with the pk set, pre-loading the form
 *
 * Every one of those ends in the *Service / Repository layer — this class and
 * its subclasses contain no SQL. `action=delete` is POST-only on purpose: a
 * GET-addressable delete would be a cross-site request forgery, which is why
 * the summary table's own Delete buttons are POST submit buttons.
 *
 * PHP 7.3 compatible — no typed properties, no PHP 8+ syntax.
 *
 * @package ksf_FA_HRM
 * @since   1.1.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §11 (app shell + tab controller SRPs)
 * @BABOK Related: FR-HRM-001 (app shell tab registration), FR-HRM-001-002 (payroll),
 *                FR-HRM-001-005 (leave balances)
 */
abstract class FilteredTabController extends AbstractTabController
{
    use TabActionRouting;

    /** @var array<string,mixed> Current filter values, normalised. */
    protected $filters = [];

    /**
     * @var bool Whether a refused mutation should keep the rejected POST values
     *           in the entry form rather than blanking it.
     */
    protected $keepPostedFormValues = false;

    /**
     * Run the tab: POST routing, then filter -> summary -> entry form.
     *
     * @return void
     *
     * @since 1.1.0
     */
    public function run(): void
    {
        $this->filters = $this->readFilters();

        // A refused `action=` has already been reported; running the POST
        // router again would replay whatever the original POST carried.
        if (!$this->actionRefused) {
            $this->handlePost();
        }

        $this->renderTabBody();
    }

    /**
     * Filter bar -> summary table -> entry form. Split out of run() so a
     * refused action can re-render the tab without re-running POST routing.
     *
     * @return void
     *
     * @since 1.1.0
     */
    protected function renderTabBody(): void
    {
        echo $this->renderStart();
        $this->renderFilterSection();
        $this->renderSummaryTable();
        echo $this->renderEntryForm();
        echo $this->renderEnd();

        $this->renderNotes();
    }

    /**
     * Re-render after a refused action: read the filters, skip POST routing.
     *
     * @return void
     *
     * @since 1.1.0
     */
    protected function renderAfterAction(): void
    {
        $this->filters = $this->readFilters();
        // A refused save should leave the user correcting what they typed, not
        // retyping it into a blank form.
        $this->keepPostedFormValues = $this->actionRefused && $this->isPost();
        $this->renderTabBody();
    }

    /**
     * The raw (untyped) filter values, for re-populating the filter controls.
     *
     * @return array<string, mixed>
     *
     * @since 1.1.0
     */
    protected function rawFilterValues(): array
    {
        $raw = [];
        foreach ($this->getFilterFields() as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name !== '') {
                $raw[$name] = $_POST[$name] ?? ($_GET[$name] ?? '');
            }
        }
        return $raw;
    }

    // ─── Filter contract ──────────────────────────────────────────────

    /**
     * Filter control definitions, in render order.
     *
     * Each entry is an array:
     *   name        (string)  POST field name, conventionally filter_<name>
     *   label       (string)  already-translated row label
     *   type        (string)  'text' | 'number' | 'date' | 'select' | 'label'
     *   options     (array)   select options (type=select only)
     *   spec_option (string)  leading "All ..." option (type=select only)
     *   size        (int)     text box width
     *   max         (int)     text box maxlength
     *   dec         (int)     decimals for type=number
     *   bold        (bool)    render the total in bold
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 1.1.0
     */
    abstract protected function getFilterFields(): array;

    /**
     * Coerce the raw filter input into typed filter values.
     *
     * @param array<string, mixed> $input  Raw `filter_*` values from the request
     *
     * @return array<string, mixed>
     *
     * @since 1.1.0
     */
    abstract protected function normaliseFilters(array $input): array;

    /**
     * Read the `filter_<name>` fields and hand them to normaliseFilters().
     *
     * Subclasses override normaliseFilters(), NOT this method: the two-step
     * split is what lets the base own the request-reading convention while a
     * tab owns the typing.
     *
     * @return array<string, mixed>
     *
     * @since 1.1.0
     */
    protected function readFilters(): array
    {
        $input = [];
        foreach ($this->getFilterFields() as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $input[$name] = $_POST[$name] ?? ($_GET[$name] ?? '');
        }
        return $this->normaliseFilters($input);
    }

    /**
     * Render the filter bar: a TABLESTYLE2 section inside the open <form>.
     *
     * The submit button is named filter_submit so it cannot be mistaken for the
     * entry form's `<pk>_submit` by AbstractTabController::handlePost() — a
     * filter click re-renders the tab rather than saving a record.
     *
     * @return void
     *
     * @since 1.1.0
     */
    protected function renderFilterSection(): void
    {
        if (!function_exists('start_table')) {
            return;
        }

        $fields = $this->getFilterFields();
        if ($fields === []) {
            return;
        }

        // Re-populate from the RAW request values, not $this->filters: the typed
        // values a tab normalises to (ints, defaults) are for querying, and
        // feeding them back into the controls would rewrite "All statuses" as 0.
        $raw = $this->rawFilterValues();

        start_table(TABLESTYLE2, 'width=95%');
        table_section_title($this->localise('Filter'));

        foreach ($fields as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $label = (string) ($field['label'] ?? $name);
            $value = $raw[$name] ?? '';

            switch ((string) ($field['type'] ?? 'text')) {
                case 'select':
                    array_selector_row($label, $name, $value, (array) ($field['options'] ?? []),
                        ['spec_option' => $field['spec_option'] ?? '']);
                    break;
                case 'number':
                    qty_row($label, $name, $value, '', isset($field['dec']) ? (int) $field['dec'] : 2);
                    break;
                case 'date':
                    $this->seedPostDate($name, $value);
                    date_row($label, $name);
                    break;
                default:
                    text_row($label, $name, (string) $value,
                        (int) ($field['size'] ?? 30), (int) ($field['max'] ?? 60));
                    break;
            }
        }

        submit_row('filter_submit', $this->localise('Filter'), false, '', false);
        end_table(1);
    }

    // ─── Summary table ────────────────────────────────────────────────

    /**
     * Render the paged SUMMARY table.
     *
     * Identical to AbstractTabController::renderSummaryTable() except that the
     * empty-list message and the row-action set are overridable, which the
     * recruitment placeholder needs (its empty list has a reason worth stating)
     * and the read-only reports tab needs (it has no rows to act on).
     *
     * @return void
     *
     * @since 1.1.0
     */
    protected function renderSummaryTable(): void
    {
        $page = max(1, $this->page());
        $rows = $this->listRows($page, $this->perPage);

        $columns = [];
        foreach ($this->getFields('showInTable') as $name => $field) {
            $columns[] = ['key' => $name, 'label' => $field['label'] ?? $name];
        }

        $pk = $this->getPkField();
        $summary = new MasterSummaryTable(
            $columns,
            $rows,
            $this->getRowActions(),
            [
                'record_id_field'       => $pk,
                'row_id_field'          => $pk,
                'record_id'             => $this->context->getRecordId(),
                'tab_sel'               => $this->tabSel(),
                'page'                  => $page,
                'per_page'              => $this->perPage,
                'total'                 => $this->countRows(),
                'form_action'           => $this->formAction(),
                'notification_div'      => $pk,
                'submit_button_name'    => $this->getSubmitName(),
                'cancel_button_name'    => $this->getCancelName(),
                'title'                 => $this->getTitle(),
                'show_footer'           => false, // the entry form below owns its footer
                'empty_message'         => $this->getEmptyMessage(),
                'action_labels'         => $this->getActionLabels(),
                'delete_confirm_message' => $this->getDeleteConfirmMessage(),
                'ajax'                  => false,
                'preserve_params'       => $this->preserveParams(),
            ]
        );
        $summary->render();
    }

    /**
     * Per-row action buttons. Read-only tabs return an empty set.
     *
     * @return array<string, bool>
     *
     * @since 1.1.0
     */
    protected function getRowActions(): array
    {
        return ['edit' => true, 'delete' => true];
    }

    /**
     * Message shown when the summary table has no rows.
     *
     * @return string
     *
     * @since 1.1.0
     */
    protected function getEmptyMessage(): string
    {
        return $this->localise('No records yet.');
    }

    // ─── Entry form rendering (FA-native, not Bootstrap) ──────────────

    /**
     * Render the entry form: the control block plus its Submit/Cancel footer.
     *
     * Overrides AbstractTabController::renderEntryForm() only to keep the
     * footer pairing explicit — the control grid itself is
     * renderEntryFormControlBlock() below.
     *
     * @return string
     *
     * @since 1.1.0
     */
    protected function renderEntryForm(): string
    {
        if (!function_exists('start_table')) {
            return '';
        }

        $pk = $this->context->getRecordId();
        $values = $pk !== '' ? ($this->findRecord($pk) ?? []) : $this->blankValues();
        if ($this->keepPostedFormValues) {
            $values = $this->postedFormValues($values);
        }

        ob_start();
        $this->renderEntryFormControlBlock($this->getFieldMetadata(), $values, $this->fkOptions());
        echo '<br>';
        $footer = new FormFooter(
            $this->getSubmitName(),
            $this->getCancelName(),
            $pk !== '' ? $this->localise('Update') : $this->localise('Save'),
            $this->localise('Cancel')
        );
        $footer->render();
        return (string) ob_get_clean();
    }

    /**
     * Overlay the rejected POST values onto the entry-form values.
     *
     * Only fields the form actually owns (showInForm, excluding the pk) are
     * taken from the request, so a tampered extra POST field cannot inject a
     * value into an unrelated control.
     *
     * @param array<string, mixed> $values Stored or blank values
     *
     * @return array<string, mixed>
     *
     * @since 1.1.0
     */
    protected function postedFormValues(array $values): array
    {
        foreach ($this->getFields('showInForm') as $name => $field) {
            if ($name === $this->getPkField() || !array_key_exists($name, $_POST)) {
                continue;
            }
            if (($field['type'] ?? '') === 'checkbox') {
                $values[$name] = !empty($_POST[$name]) ? 1 : 0;
                continue;
            }
            $values[$name] = $_POST[$name];
        }
        return $values;
    }

    /**
     * Render the entry-form control grid with FA's own row helpers.
     *
     * Overrides AbstractTabController::renderEntryFormControlBlock(), whose
     * default path hands the job to FieldForm and therefore emits Bootstrap
     * grid markup. The four tabs consolidated out of pages/*.php were rebuilt
     * on FA's ui_input.inc helpers, so they keep rendering that way here.
     *
     * @param array<string, mixed> $metadata  Full field metadata
     * @param array<string, mixed> $values    Pre-filled (update) or blank (add)
     * @param array<string, mixed> $fkOptions  Field name => options
     *
     * @return void
     *
     * @since 1.1.0
     */
    protected function renderEntryFormControlBlock(array $metadata, array $values, array $fkOptions): void
    {
        if (!function_exists('start_table')) {
            return;
        }

        start_table(TABLESTYLE2, 'width=95%');
        table_section_title($this->getEntryFormTitle());

        foreach ($this->getFields('showInForm') as $name => $field) {
            $label = (string) ($field['label'] ?? $name);
            $value = $values[$name] ?? ($field['default'] ?? '');

            switch ((string) ($field['type'] ?? 'text')) {
                case 'select':
                    array_selector_row($label, $name, (string) $value,
                        (array) ($fkOptions[$name] ?? []));
                    break;
                case 'number':
                    amount_row($label, $name, $value, '',
                        isset($field['dec']) ? (int) $field['dec'] : 2);
                    break;
                case 'date':
                    $this->seedPostDate($name, (string) $value);
                    date_row($label, $name);
                    break;
                case 'checkbox':
                    check_row($label, $name, (bool) $value);
                    break;
                case 'label':
                    label_row($label, $this->formatLabelField((string) $field['format'] ?? '', $value));
                    break;
                default:
                    text_row($label, $name, (string) $value,
                        (int) ($field['max'] ?? 40), (int) ($field['max'] ?? 40));
                    break;
            }
        }

        submit_add_or_update_row($this->context->getRecordId() === '', false, '', false, false);
        end_table(1);
    }

    /**
     * Heading above the entry form's control grid.
     *
     * @return string
     *
     * @since 1.1.0
     */
    protected function getEntryFormTitle(): string
    {
        return $this->context->getRecordId() === ''
            ? $this->localise('Add')
            : $this->localise('Edit');
    }

    /**
     * Format a read-only ('label' type) entry-form field.
     *
     * @param string $format 'price' | 'qty' | ''
     * @param mixed  $value  Raw value
     *
     * @return string
     *
     * @since 1.1.0
     */
    protected function formatLabelField(string $format, $value): string
    {
        switch ($format) {
            case 'price':
                return function_exists('price_format') ? price_format((float) $value) : (string) $value;
            case 'qty':
                return function_exists('number_format')
                    ? number_format((float) $value, 2) : (string) $value;
            default:
                return (string) $value;
        }
    }

    /**
     * Seed $_POST for a date field so FA's date_row() renders the value we
     * want instead of substituting today's date into an empty field.
     *
     * FA's date_cells() reads $_POST and, when the field is absent, writes the
     * current date — which would silently turn "no value" into a value.
     *
     * @param string $name  Field name
     * @param string $value Value to render
     *
     * @return void
     *
     * @since 1.1.0
     */
    protected function seedPostDate(string $name, string $value): void
    {
        if ($value !== '') {
            $_POST[$name] = $value;
        }
    }

    /**
     * Footnote area rendered after the form. Default: nothing.
     *
     * @return void
     *
     * @since 1.1.0
     */
    protected function renderNotes(): void
    {
    }

    // ─── PRG ──────────────────────────────────────────────────────────

    /**
     * Redirect after a successful action (PRG), carrying the active filter so
     * the user lands back on the list they were looking at.
     *
     * TabActionRouting supplies the base behaviour; the only addition is
     * appending the filter values to the redirect target.
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

        $url = $this->appendFilters($this->hrmActionUrl([]));
        if ($url !== '') {
            $this->hrmRedirect($url);
        }
    }

    /**
     * Merge the current filter values into a URL's query string, replacing any
     * filter parameters already present so a stale value never survives.
     *
     * @param string $url
     *
     * @return string
     *
     * @since 1.1.0
     */
    protected function appendFilters(string $url): string
    {
        $parts = explode('?', $url, 2);
        $query = [];
        if (isset($parts[1]) && $parts[1] !== '') {
            parse_str($parts[1], $query);
        }

        foreach (array_keys($this->getFilterFields()) as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name !== '') {
                unset($query[$name]);
            }
        }
        foreach ($this->rawFilterValues() as $name => $value) {
            if ($value !== '' && $value !== null) {
                $query[$name] = $value;
            }
        }

        return $parts[0] . '?' . http_build_query($query);
    }
}
