<?php
/**
 * Created by PhpStorm.
 * User: tungquach
 * Date: 18/12/2017
 * Time: 17:19
 */

namespace BCB\Data;


class Event
{
    const PLUGIN_ACTIVATION = 'WC Activate plugin';
    const PLUGIN_FIRST_ACTIVATE = 'WC 1st Activate plugin';
    const PLUGIN_DEACTIVATION = 'WC Inactivate plugin';
    const PLUGIN_UNINSTALL = 'WC Delete plugin';
}