<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Version_107 extends App_module_migration
{
    public function up()
    {
        if (table_exists('client_plan')) {
            if (!get_instance()->db->field_exists('is_force_redirect', db_prefix() . 'client_plan')) {
                get_instance()->db->query('ALTER TABLE ' . db_prefix() . 'client_plan ADD is_force_redirect INT NOT NULL DEFAULT "0" AFTER is_active;');
            }
        }
    }
}
