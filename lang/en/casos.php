<?php

return [
    // Page titles
    'title_list' => 'Project Cases',
    'title_create' => 'New Case',
    'title_edit' => 'Edit Case',
    'title_work' => 'Work View',

    // Subtitles / meta
    'subtitle_open' => ':count open cases',
    'subtitle_type' => 'Project type: :tipo',
    'subtitle_person' => 'Person: :nombre',
    'subtitle_type_edit' => 'Type: :tipo',
    'subtitle_state_via' => 'Status: modified via interactions',

    // Actions / buttons
    'create_case' => 'Create Case',
    'save_changes' => 'Save Changes',
    'back_to_tray' => '← Back to tray',
    'new_case' => 'New Case',
    'edit_case' => 'Edit Case',

    // Form fields
    'field_wallet' => 'Portfolio',
    'field_priority' => 'Priority (0–9)',
    'field_entry_date' => 'Entry Date',
    'select_wallet' => '— Select —',

    // Additional info
    'additional_info' => 'Additional Case Information',
    'no_custom_fields' => '(no fields defined by the administrator for this portfolio)',
    'custom_fields_title' => 'Custom Fields',
    'case_fields_title' => 'Case Fields',

    // No person alert
    'no_person_alert' => 'Select a person from the list to create a case. The screen expects <code>?persona={ulid}</code>.',

    // Filters / search bar
    'search_placeholder' => 'Search by person…',
    'all_wallets' => 'All portfolios',
    'all_states' => 'All statuses',
    'clear_filters' => 'Clear',
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
    'empty_title' => 'No Cases',
    'empty_no_filters' => 'There are no cases in this project yet.',
    'empty_with_filters' => 'No cases match the current filters.',

    // Commitment badge
    'commitment_active' => 'Active',

    // Work view — left panel
    'cases_count' => 'Cases (:count)',
    'active_commitment' => 'Active Commitment',
    'expires' => 'Expires :date',
    'active_commitment_edit' => 'Edit',
    'no_open_cases' => 'No open cases',
    'no_open_cases_desc' => 'This person has no cases in this project yet.',
    'resolved_commitments' => 'Resolved Commitments (:count)',
    'expiry_label' => 'Expiry: :date',
    'resolved_label' => 'Resolved',
    'no_date' => 'no date',
    'prio_label' => 'prio :value',
    'active_commitment_label' => 'active commitment',
    'contacts_button' => 'Contacts',
    'register_gestion_title' => 'Log Interaction',
    'select_case_title' => 'Select a Case',
    'select_case_desc' => 'Choose a case from the list to log interactions.',
    'history_title' => 'History (:count)',
    'no_gestions' => 'No interactions',
    'no_gestions_desc' => 'No interactions have been logged yet.',
    'custom_fields_panel' => 'Custom Fields',
    'no_active_case' => 'No active case',
    'no_active_case_desc' => 'Select a case to view its custom fields and history.',
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
    'notes_placeholder' => 'Free-form supplement. Do not extract data from here; use the structured fields.',
    'ctrl_enter_hint' => 'Ctrl+Enter to save.',
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
];
