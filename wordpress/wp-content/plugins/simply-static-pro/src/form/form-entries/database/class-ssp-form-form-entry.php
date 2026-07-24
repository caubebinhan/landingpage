<?php

namespace simply_static_pro\database\form_entries\models;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FormEntry extends Model {
    protected static $table_name  = 'form_entries';
    protected static $primary_key = 'id';

    // Mirrors helper schema; adjust via filters/actions if needed.
    protected static $columns = array(
        'id'          => '%d',
        'title'       => '%s',
        'form_id'     => '%s',
        'form_plugin' => '%s',
        'posted'      => '%s',
        'created_at'  => '%s',
        'updated_at'  => '%s',
    );
}
