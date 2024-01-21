<?php

defined('BASEPATH') || exit('No direct script access allowed');

require_once __DIR__ . '/SaasRestController.php';

class Plans extends \SaasApi\SaasRestController
{

    public function __construct()
    {
        parent::__construct();

        register_language_files('saas');
        load_client_language();

        $this->load->model('custom_model');
        $this->load->library('app_modules');
        $this->load->helper('superadmin');

        if (isAuthorized()) {
            $this->response(isAuthorized()['response'], isAuthorized()['response_code']);
        }
    }

    /**
     * @api {get} /saas/api/plans/:id Get Plan By ID
     *
     * @apiVersion 1.0.0
     *
     * @apiName GetPlanById
     *
     * @apiGroup Plans
     *
     * @apiSampleRequest off
     *
     * @apiHeader {String} Authorization <span class="btn btn-xs btn-danger">Required</span> Basic Access Authentication token.
     *
     * @apiParam {Number} id <span class="btn btn-xs btn-danger">Required</span> Plan ID.
     *
     * @apiSuccess {Boolean} status  Response status.
     * @apiSuccess {Object}  data    Plan information.
     * @apiSuccess {String}  message Success message.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *         "status": true,
     *         "data": [
     *             {
     *                 "id": "1",
     *                 "plan_name": "SaaS Plan 1",
     *                 "plan_description": "test plan 1",
     *                 "plan_image": "",
     *                 "price": "45.00",
     *                 "price_2": "0.00",
     *                 "trial": false,
     *                 "most_popular": false,
     *                 "limitations": {
     *                     "per_plan_invoices": "5",
     *                     "per_plan_customers": "5",
     *                     "per_plan_contracts": "5",
     *                     "per_plan_projects": "5",
     *                     "per_plan_estimates": "5",
     *                     "per_plan_credit_notes": "5",
     *                     "per_plan_payments": "5",
     *                     "per_plan_items": "5",
     *                     "per_plan_proposals": "5",
     *                     "per_plan_expenses": "5",
     *                     "per_plan_tasks": "5",
     *                     "per_plan_support_tickets": "5",
     *                     "per_plan_leads": "5",
     *                     "per_plan_staff": 0
     *                 },
     *                 "allowed_payment_modes": [
     *                     "Bank"
     *                 ],
     *                 "taxes": [],
     *                 "custom_recurring": "1",
     *                 "cycles": "0",
     *                 "allowed_modules": {
     *                     "menu_setup": true
     *                 },
     *                 "recurring_time": "3 day",
     *                 "plan_url": "http://my-awesome-website.com/authentication/register?plan=1"
     *             }
     *         ],
     *         "message": "Data Retrived Successfully"
     *     }
     *
     * @apiError {Boolean} status  Response status.
     * @apiError {Array} data   Array.
     * @apiError {String}  message Error message.
     *
     * @apiErrorExample Error-Response:
     *     HTTP/1.1 404 Not Found
     *     {
     *       "status": false,
     *       "data": [],
     *       "message": "Data Not Found"
     *     }
     */

    /**
     * @api {get} /saas/api/plans List All Plans
     *
     * @apiVersion 1.0.0
     *
     * @apiName GetAllPlans
     *
     * @apiGroup Plans
     *
     * @apiSampleRequest off
     *
     * @apiHeader {String} Authorization <span class="btn btn-xs btn-danger">Required</span> Basic Access Authentication token.
     *
     * @apiSuccess {Boolean} status  Response status.
     * @apiSuccess {Array}   data    Plans information.
     * @apiSuccess {String}  message Success message.
     *
     * @apiSuccessExample Success-Response:
     *      HTTP/1.1 200 OK
     *      {
     *          "status": true,
     *          "data": [
     *              {
     *                  "id": "1",
     *                  "plan_name": "SaaS Plan 1",
     *                  "price": "45.00",
     *                  "allowed_payment_modes": [
     *                      "Bank"
     *                  ],
     *                  "plan_image": "",
     *                  "recurring_time": "3 day",
     *                  "plan_url": "http://my-awesome-website.com/authentication/register?plan=1"
     *              },
     *              ...
     *          ],
     *          "message": "Data Retrived Successfully"
     *      }
     *
     * @apiError {Boolean} status  Response status.
     * @apiError {Array} data   Array.
     * @apiError {String}  message Error message.
     *
     * @apiErrorExample Error-Response:
     *     HTTP/1.1 404 Not Found
     *     {
     *         "status": false,
     *         "data": [],
     *         "message": "No data found"
     *     }
     */
    public function plans_get($id = null)
    {
        $columns = ($id != null) ? [] : ['id', 'plan_name', 'price', 'allowed_payment_modes', 'recurring', 'recurring_type', 'plan_image'];
        $where = ($id != null) ? ['id' => $id] : [];

        $planData = $this->custom_model->getRows(db_prefix() . 'plan_management', $where, [], null, $columns, 'array');

        if ($planData) {
            foreach ($planData as &$value) {
                $value['allowed_payment_modes'] = array_map(
                    fn ($val) => is_numeric($val) ? $this->custom_model->getSingleValue(db_prefix() . 'payment_modes', 'name', ['id' => $val]) : $val,
                    unserialize($value['allowed_payment_modes'])
                );

                if ($id) {
                    $keysToConvert = ['allowed_modules', 'trial', 'most_popular', 'limitations', 'taxes'];
                    foreach ($keysToConvert as $key) {
                        if (isset($value[$key])) {
                            $parsedValue = $key === 'allowed_modules'
                                    ? array_map(fn ($v) => $v === 1, unserialize($value[$key]))
                                    : $value[$key] === "1";
                            if($key == "limitations"){
                                $parsedValue = json_decode($value[$key]);
                            }
                            if($key == "taxes"){
                                $taxname = unserialize($value[$key]);
                                if($taxname){
                                    foreach ($taxname as $tax) {
                                        $tax_array   = explode('|', $tax);
                                        $taxes[] = ['name' => $tax_array[0], 'taxrate' => $tax_array[1]];
                                    }
                                }
                                $parsedValue = $taxes ?? [];
                            }
                            $value[$key] = $parsedValue;
                        }
                    }
                }

                $value['recurring_time'] = $value['recurring'] . ' ' . $value['recurring_type'];
                $value['plan_url'] = base_url('authentication/register?plan=' . $value['id']);
                $value['plan_image'] = !empty($value['plan_image']) ? base_url('modules/saas/uploads/' . $value['plan_image']) : '';
                unset($value['recurring']);
                unset($value['recurring_type']);
            }
            $this->response([
                'data'    => $planData,
                'message' => _l('data_retrived_success')
            ], 200);
        }

        $this->response(['data' => [], 'message' => _l('data_not_found')], 404);
    }
}
