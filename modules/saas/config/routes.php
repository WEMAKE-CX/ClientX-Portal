<?php

defined('BASEPATH') || exit('No direct script access allowed');

$route['saas/(.*)/(.*)/(.*)'] = '$1/$2/$2/$3';
$route['saas/(:any)/(:any)']  = '$1/$2/$2';

$route['saas/terms-of-use'] = 'terms_of_use';
