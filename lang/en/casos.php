<?php

return [
    'pick_type_first' => 'Pick the type first',
    'fields_ungrouped' => 'Ungrouped',
    'currency_symbol' => 'USD',
    'entidad_singular' => [
        'cobranza' => 'account',
        'cx' => 'ticket',
        'venta' => 'opportunity',
        'servicio' => 'service',
        'generico' => 'case',
    ],
    'entidad_plural' => [
        'cobranza' => 'accounts',
        'cx' => 'tickets',
        'venta' => 'opportunities',
        'servicio' => 'services',
        'generico' => 'cases',
    ],
    'entidad_articulo' => [
        'cobranza' => 'an',
        'cx' => 'a',
        'venta' => 'an',
        'servicio' => 'a',
        'generico' => 'a',
    ],
    // Page titles
    'title_list' => 'Project :entidades',
    'title_create' => 'Create :entidad',
    'title_edit' => 'Edit :entidad',
    'title_work' => 'Work View',

    // Subtitles / meta
    'subtitle_open' => ':count :entidades',
    'subtitle_type' => 'Project type: :tipo',
    'subtitle_person' => 'Person: :nombre',
    'subtitle_type_edit' => 'Type: :tipo',
    'subtitle_state_via' => 'Status: modified via interactions',

    // Actions / buttons
    'create_case' => 'Create :entidad',
    'save_changes' => 'Save Changes',
    'back_to_tray' => '← Back to tray',
    'new_case' => 'Create :entidad',
    'edit_case' => 'Edit :entidad',

    // Form fields
    'field_wallet' => 'Portfolio',
    'field_priority' => 'Priority (0–9)',
    'field_entry_date' => 'Entry Date',
    'select_wallet' => '— Select —',

    // Additional info
    'additional_info' => 'Additional information',
    'no_custom_fields' => '(no fields defined by the administrator for this portfolio)',
    'custom_fields_title' => 'Custom Fields',
    'case_fields_title' => ':Entidad fields',

    // No person alert
    'no_person_alert' => 'Select a person from the list to create :un :entidad. The screen expects <code>?persona={ulid}</code>.',

    // Filters / search bar
    'search_placeholder' => 'Search by person…',
    'all_wallets' => 'All portfolios',
    'all_states' => 'All statuses',
    'clear_filters' => 'Clear',
    'export_csv' => 'Export CSV',
    'results' => ':count results',

    // Table columns
    'col_type' => 'Type',
    'col_person' => 'Person',
    'col_id_doc' => 'Identification',
    'col_wallet' => 'Portfolio',
    'col_state' => 'Status',
    'col_priority' => 'Prio',
    'col_commitment' => 'Commitment',

    // Empty state
    'empty_title' => 'No :entidades',
    'empty_no_filters' => 'There are no :entidades in this project yet.',
    'empty_with_filters' => 'No :entidades match the current filters.',

    // Commitment badge
    'commitment_active' => 'Active',

    // Work view — left panel
    'cases_count' => ':Entidades (:count)',
    'active_commitment' => 'Active Commitment',
    'expires' => 'Expires :date',
    'active_commitment_edit' => 'Edit',
    'no_open_cases' => 'No :entidades',
    'no_open_cases_desc' => 'This person has no :entidades in this project yet.',
    'resolved_commitments' => 'Resolved Commitments (:count)',
    'expiry_label' => 'Expiry: :date',
    'resolved_label' => 'Resolved',
    'no_date' => 'no date',
    'prio_label' => 'prio :value',
    'active_commitment_label' => 'active commitment',
    'contacts_button' => 'Contacts',
    'register_gestion_title' => 'Log Interaction',
    'select_case_title' => 'Select :un :entidad',
    'select_case_desc' => 'Choose :un :entidad from the list to log interactions.',
    'history_title' => 'History (:count)',
    'no_gestions' => 'No interactions',
    'no_gestions_desc' => 'No interactions have been logged yet.',
    'custom_fields_panel' => 'Custom Fields',
    'no_active_case' => 'Nothing selected',
    'no_active_case_desc' => 'Select :un :entidad to view its custom fields and history.',
    'no_contact_badge' => 'No contact: :motivo',
    'cause_badge' => 'Cause: :causa',

    // New interaction
    'gestion_title' => 'New Interaction',
    'field_channel' => 'Channel',
    'field_gestion_type' => 'Interaction Type',
    'field_result' => 'Result',
    'field_contact_used' => 'Contact Used',
    'field_no_contact_reason' => 'No-Contact Reason',
    'field_cause' => 'Cause',
    'field_duration' => 'Duration (sec)',
    'field_notes' => 'Notes (optional)',
    'notes_placeholder' => 'What happened, in one sentence. Data the business filters or counts goes in the fields above, not here.',
    'ctrl_enter_hint' => 'Ctrl+Enter or ⌘+Enter to save.',
    'submit_gestion' => 'Log Interaction',

    // Commitments inline in new interaction
    'promise_title' => 'Payment Promise',
    'promise_amount' => 'Amount USD',
    'promise_date' => 'Date',
    'promise_payment_type' => 'Payment Type',
    'close_title' => 'Closing Commitment',
    'close_amount' => 'Amount USD',
    'close_estimated_date' => 'Estimated Date',
    'close_funnel_stage' => 'Funnel Stage',
    'service_action_title' => 'Scheduled Service Action',
    'service_action_desc' => 'Action Description',
    'service_action_desc_ph' => 'E.g. Equipment installation at premises',
    'service_scheduled_date' => 'Scheduled Date',
    'service_action_type' => 'Action Type',
    'service_technician' => 'Assigned Technician',
    'service_technician_ph' => 'Technician name',
    'resolution_title' => 'Resolution Commitment',
    'resolution_action' => 'Committed Action',
    'resolution_action_ph' => 'E.g. Review billing and call the customer',
    'resolution_deadline' => 'Deadline',
    'escalation_section' => 'Escalation',
    'escalation_level' => 'Level',
    'yes' => 'Yes',
    'no' => 'No',
    'born_abbrev' => 'b.',

    // F41: selector de columnas del listado
    'columns' => 'Columns',
    'columns_title' => 'Visible columns',
    'columns_reset' => 'Reset',
    // Self-assignment: the agent takes the account they are about to work
    'assign_take' => 'Take',
    'assign_take_title' => 'Take this account into my queue',
    'assign_taken' => 'Account taken. It is now in your queue.',
    'assign_owner' => 'Assigned to',
    'assign_unowned' => 'Unassigned',
    'assign_only_unowned' => 'Unassigned only',
];
