<?php

namespace simply_static_pro\database\form_entries\queries;

use simply_static_pro\database\form_entries\models\Model;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Query {
    /** @var string Fully-qualified model class name */
    protected $model;
    protected $limit  = null;
    protected $offset = null;
    protected $where  = [];
    protected $order  = '';

    public function __construct( $model_class ) {
        $this->model = $model_class;
    }

    public function limit( $limit ) { $this->limit = absint( $limit ); return $this; }
    public function offset( $offset ) { $this->offset = absint( $offset ); return $this; }
    public function where( $conditions ) { $this->where[] = $conditions; return $this; }
    public function order( $order ) { $this->order = $order; return $this; }

    protected function compose_where_sql() : string {
        global $wpdb;
        if ( empty( $this->where ) ) { return ''; }
        $clauses = [];
        foreach ( $this->where as $cond ) {
            if ( is_array( $cond ) ) {
                foreach ( $cond as $col => $val ) {
                    $clauses[] = $wpdb->prepare( "`$col` = %s", $val );
                }
            } elseif ( is_string( $cond ) ) {
                $clauses[] = $cond; // Raw fragment, use carefully.
            }
        }
        return ' WHERE ' . implode( ' AND ', $clauses ) . ' ';
    }

    public function find() {
        global $wpdb;
        /** @var Model $model */
        $model = $this->model;
        $table = $model::table_name();
        $sql = 'SELECT * FROM ' . $table;
        $sql .= $this->compose_where_sql();
        if ( $this->order ) { $sql .= ' ORDER BY ' . esc_sql( $this->order ) . ' '; }
        if ( null !== $this->limit ) { $sql .= ' LIMIT ' . intval( $this->limit ); }
        if ( null !== $this->offset ) { $sql .= ' OFFSET ' . intval( $this->offset ); }
        $rows = $wpdb->get_results( $sql, ARRAY_A );
        if ( empty( $rows ) ) { return []; }
        $records = [];
        foreach ( $rows as $row ) { $records[] = $model::initialize( $row ); }
        return $records;
    }

    public function count() : int {
        global $wpdb;
        $model = $this->model;
        $table = $model::table_name();
        $sql = 'SELECT COUNT(*) FROM ' . $table;
        $sql .= $this->compose_where_sql();
        return (int) $wpdb->get_var( $sql );
    }

    public function find_by( $column, $value ) {
        $this->where( [ $column => $value ] )->limit( 1 );
        $results = $this->find();
        return ! empty( $results ) ? $results[0] : null;
    }

    public function delete_by( $column, $value ) : int {
        global $wpdb;
        $model = $this->model;
        $table = $model::table_name();
        return (int) $wpdb->delete( $table, [ $column => $value ] );
    }
}
