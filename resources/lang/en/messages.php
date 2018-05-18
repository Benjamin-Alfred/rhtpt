<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Messages/notes used in this application
    |--------------------------------------------------------------------------
    |
    | The following lines are used in various places in this application
    | e.g. director's note
    |
    */
    /**
    *   Main menu
    */
    'dashboard'             =>    'Dashboard',
    'pt'                    =>    'Proficiency Testing',
    'user-management'       =>    'User Management',
    'facility-catalog'      =>    'Facility Catalog',
    'program-management'    =>    'Questionnaire',
    'reports'               =>    'Reports',
    'config'                =>    'Configs',

    /**
    *   Sub-menus
    */
    //  PT
    'program'               =>    'Program|Programs',
    'shipper'               =>    'Shipping Agent|Shipping Agents',
    'material'              =>    'Material|Materials',
    'sample-preparation'    =>    'Sample Preparation',
    'pt-round'              =>    'PT Round|PT Rounds',
    'panel'                 =>    'Panel|Panels',
    'expected-result'       =>    'Expected Result|Expected Results',
    'shipment'              =>    'Shipment|Shipments',
    'receipt'               =>    'Receipt|Receipts',
    'result'                =>    'Result|Results',
    'partner'               =>    'Partner|Partners',
    'lot'                   =>    'Lot|Lots',
    'registration'          =>    'Registration|Registrations',
    'count'                 =>    'Count|Counts',
    /* Views */
    'batch'                 =>    'Batch No.',
    'date-prepared'         =>    'Date Prepared',
    'expiry-date'           =>    'Expiry Date',
    'material-type'         =>    'Material Type',
    'original-source'       =>    'Original Source',
    'date-collected'        =>    'Date Collected',
    'prepared-by'           =>    'Prepared By',
    'start-date'            =>    'Begins',
    'end-date'              =>    'Ends',
    'pt-id'                 =>    'PT Identifier',
    'tested-by'             =>    'Tested By',
    'select'                =>    '--- Select ---',
    'date-shipped'          =>    'Date Shipped',
    'shipping-method'       =>    'Shipping Method|Shipping Methods',
    'courier'               =>    'Courier|Couriers',
    'participant'           =>    'Participant|Participants',
    'panels-shipped'        =>    'Panels Shipped',
    'date-received'         =>    'Date Received',
    'panels-received'       =>    'Panels Received',
    'condition'             =>    'Condition',
    'storage'               =>    'Storage',
    'transit-temperature'   =>    'Transit Temperature',
    'recipient'             =>    'Recipient|Recipients',
    'shipper-type'          =>    'Shipper Type|Shipper Types',
    'contact'               =>    'Contact|Contacts',
    'tester-id'             =>    'Tester ID',
    'tester-id-range'       =>    'Tester ID Range',
    'feedback'              =>    'Feedback',
    //  Reports

    //  User Management
    'user'                  =>    'User|Users',
    'role'                  =>    'Role|Roles',
    'permission'            =>    'Permission|Permissions',
    'assign-roles'          =>    'Assign Roles',
    /* Views */
    'uid'                   =>    'Unique ID',
    //  Facility Catalog - Administrative and are not bound to change in the near future
    'sub-county'            =>    'Sub County|Sub Counties',
    'county'                =>    'County|Counties',
    'facility'              =>    'Facility|Facilities',
    /* Views */
    'code'                  =>    'MFL Code',
    'mailing-address'       =>    'Mailing Address',
    'in-charge'             =>    'In Charge',
    'in-charge-phone'       =>    'In Charge Phone',
    'in-charge-email'       =>    'In Charge Email',
    'longitude'             =>    'Longitude',
    'latitude'              =>    'Latitude',
    'mfl-code'              =>    'MFL Code',

    //  Program Management
    'field-set'             =>     'Field Set|Field Sets',
    'option'                =>     'Option|Options',
    'field'                 =>     'Field|Fields',
    'nonperf'               =>     'Non-performance|Non-performance',
    /* Views */
    'order'                 =>    'Order',
    'tag'                   =>    'Tag',
    'matrix'                =>    'Matrix',
    'test'                  =>    'Test|Tests',
    'result'                =>    'Result|Results',

    //  Breadcrumb
    'home'                  =>      'Home',
    'add'                   =>      'Add New',
    'edit'                  =>      'Edit Record',
    'view'                  =>      'View Record',

    'name'  =>  'Name',
    'label' =>  'Label',
    'url'   =>  'URL',
    'phone' =>  'Phone',
    'address'   =>  'Address',
    'email'     =>  'Email',
    'location'  =>  'Location',
    'sendtoemail'   =>  'Send to Email',
    'punchline' =>  'Punchline',
    'mapcode'   =>  'Map Code',
    'heading'   =>  'Heading',
    'mobile'    =>  'Mobile',
    'position'  =>  'Position',
    'status'    =>  'Status',
    'add'       =>  'Add New',
    'back'      =>  'Back',
    'delete'    =>  'Delete',
    'close'     =>  'Close',
    'save'      =>  'Save',
    'update'    =>  'Update',
    'page'      =>  'Existing Page',
    'cancel'    =>  'Cancel',
    'action'    =>  'Action(s)',
    'hierarchy' =>  'Hierarchy',
    'image'     =>  'Image',
    'active'    =>  'Active',
    'inactive'  =>  'Inactive',
    'disable'   =>  'Disable',
    'enable'    =>  'Enable',
    /**
    *   Status messages
    */
    'record-successfully-saved'     =>  'The record was successfully saved.',
    'record-successfully-updated'   =>  'The record was successfully updated.',
    'record-successfully-deleted'   =>  'The record was successfully deleted.',
    'failure-delete-record'         =>  'Encountered error while attempting to delete record',
    'no-records-found'              =>  'No records found.',
    'message-successfully-sent'     =>  'Message successfully sent.',
    /**
    *   Other terms
    */
    'description'       =>  'Description',
    'display-name'      =>  'Display Name',
    'full-name'         =>  'Full Name',
    'username'          =>  'Username',
    'gender'            =>  'Gender',
    'male'              =>  'Male',
    'female'            =>  'Female',
    'use-default'       =>  'Default Password',
    'profile-photo'     =>  'Profile Photo',
    'no-photo'          =>  'No Photo',
    'password'          =>  'Password',
    'email-address'     =>  'Email Address',
    'nphl'              =>  'NPHL',
    /**
    *   Bulk SMS
    */
    'bulk-sms'          =>  'Bulk SMS',
    'api-key'           =>  'API Key',
    'message'           =>  'Message',
    'cost'              =>  'Cost',
    'date-sent'         =>  'Date Sent',
    'settings'          =>  'Settings',
    'broadcast'         =>  'Broadcast',
    'sms'               =>  'SMS',
    'sender'            =>  'Sender',
    'total'             =>  'Total',
    'expand'            =>  'Expand',
    'status'            =>  'Status',
    'compose'           =>  'Compose',
    'code'              =>  'Code',
    /**
    *   Results
    */
    'enter-result'          =>  'Enter Results',
    'verify-result'          =>  'Verify Results',
    /**
    *   User Profile
    */
    'user-profile'          =>  'User Profile',
];
