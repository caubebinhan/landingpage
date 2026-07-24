<?php

namespace simply_static_pro\database\form_entries\models;

use simply_static_pro\database\form_entries\queries\Query;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class Model {

    /** @var string Table short name without prefix */
    protected static $table_name = '';

    /** @var array Columns definition map */
    protected static $columns = array();

    /** @var string Primary key column */
    protected static $primary_key = 'id';

    /** @var array Loaded data */
    private $data = array();

    /** @var array Dirty fields */
    private $dirty_fields = array();

    public function __get( $field_name ) {
        if ( ! array_key_exists( $field_name, $this->data ) ) {
            return null;
        }
        return $this->data[ $field_name ];
    }

    public function __set( $field_name, $field_value ) {
        if ( ! array_key_exists( $field_name, static::$columns ) ) {
            // Allow dynamic columns but do not mark dirty if unknown
            $this->data[ $field_name ] = $field_value;
            return $field_value;
        }

        if ( ! array_key_exists( $field_name, $this->data ) || $this->data[ $field_name ] !== $field_value ) {
            $this->dirty_fields[] = $field_name;
        }
        return $this->data[ $field_name ] = $field_value;
    }

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'simply_static_' . static::$table_name;
    }

    public static function query() {
        return new Query( get_called_class() );
    }

    public static function initialize( $attributes ) {
        $obj = new static();
        foreach ( array_keys( static::$columns ) as $column ) {
            $obj->data[ $column ] = null;
        }
        $obj->attributes( $attributes );
        return $obj;
    }

    public function attributes( $attributes ) {
        foreach ( $attributes as $name => $value ) {
            $this->$name = $value;
        }
        return $this;
    }

    public function formatted_datetime() {
        return current_time( 'Y-m-d H:i:s' );
    }

    public function exists() {
        $pk = static::$primary_key;
        return ! empty( $this->$pk );
    }

    public function save() {
        global $wpdb;

        if ( $this->created_at === null ) {
            $this->created_at = $this->formatted_datetime();
        }
        $this->updated_at = $this->formatted_datetime();

        if ( empty( $this->dirty_fields ) ) {
            return true;
        } else {
            $fields             = array_intersect_key( $this->data, array_flip( $this->dirty_fields ) );
            $this->dirty_fields = array();
        }

        if ( $this->exists() ) {
            $primary_key  = static::$primary_key;
            $rows_updated = $wpdb->update( self::table_name(), $fields, array( $primary_key => $this->$primary_key ) );
            return $rows_updated !== false;
        } else {
            $rows_updated = $wpdb->insert( self::table_name(), $fields );
            if ( $rows_updated === false ) {
                return false;
            } else {
                $this->id = $wpdb->insert_id;
                return true;
            }
        }
    }
}
