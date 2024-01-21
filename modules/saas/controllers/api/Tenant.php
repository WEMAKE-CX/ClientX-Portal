<?php

defined('BASEPATH') || exit('No direct script access allowed');

require_once __DIR__ . '/SaasRestController.php';

class Tenant extends \SaasApi\SaasRestController
{

    public function __construct()
    {
        parent::__construct();

        register_language_files('saas');
        load_client_language();

        $this->load->library('form_validation');
        $this->load->model('custom_model');

        $this->load->helper(['superadmin', 'countries']);

        if (isAuthorized()) {
            $this->response(isAuthorized()['response'], isAuthorized()['response_code']);
        }
    }

    /**
     * @api {post} /saas/api/tenant Register
     *
     * @apiName Register
     *
     * @apiGroup Tenant
     * 
     * @apiHeader {String} Authorization <span class="btn btn-xs btn-danger">Required</span> Basic Access Authentication token.
     *
     * @apiVersion 1.0.0
     *
     * @apiSampleRequest off
     *
     * @apiBody {String} company               <span class="btn btn-xs btn-danger">Required</span> Company
     * @apiBody {String} firstname             <span class="btn btn-xs btn-danger">Required</span> Firstname
     * @apiBody {String} lastname              <span class="btn btn-xs btn-danger">Required</span> Lastname
     * @apiBody {String} email                 <span class="btn btn-xs btn-danger">Required</span> Email
     * @apiBody {String} password              <span class="btn btn-xs btn-danger">Required</span> Password
     * @apiBody {String} tenants_name          <span class="btn btn-xs btn-danger">Required</span> <span class="btn btn-xs btn-info">Unique</span> Tenant Name
     * @apiBody {String} [vat]                 VAT Number
     * @apiBody {Number} [phonenumber]         Phone
     * @apiBody {Number} [contact_phonenumber] Contact Phonenumber
     * @apiBody {String} [website]             Website
     * @apiBody {String} [position]            Position
     * @apiBody {Number} [country]             Country
     * @apiBody {String} [city]                City
     * @apiBody {String} [address]             Address
     * @apiBody {Number} [zip]                 Zip Code
     * @apiBody {String} [state]               State
     *
     * @apiSuccess {Boolean} status  Response status.
     * @apiSuccess {String}  message Success message.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *         "status": true,
     *         "message": "Tenant created successfully"
     *     }
     * 
     *     OR
     * 
     *     HTTP/1.1 200 OK
     *     {
     *         "status": true,
     *         "message": "A verification email has been sent to the registered email address, verify it in order to use the available features."
     *     }
     */
    public function tenant_post()
    {
        /* Check is registration allowed */
        if (1 != get_option('allow_registration')) {
            $this->response(['message' => _l('registration_not_enabled_using_api')], 403);
        }

        $requiredData = [
            'firstname'    => '',
            'lastname'     => '',
            'email'        => '',
            'password'     => '',
            'planid'       => '',
            'company'      => '',
            'tenants_name' => ''
        ];

        $postData = $this->post();
        $postData = array_merge($requiredData, $postData);

        $this->form_validation->set_data($postData);

        $this->load->library([
            'mails/app_mail_template',
            'merge_fields/app_merge_fields',
            'app_object_cache',
            'saas/superadmin_lib'
        ]);

        $this->form_validation->set_rules('firstname', _l('client_firstname'), 'required');
        $this->form_validation->set_rules('lastname', _l('client_lastname'), 'required');
        $this->form_validation->set_rules('email', _l('client_email'), 'trim|required|is_unique[' . db_prefix() . 'contacts.email]|valid_email');
        $this->form_validation->set_rules('password', _l('clients_register_password'), 'required');
        $this->form_validation->set_rules('planid', _l('plan_id'), 'required');
        $this->form_validation->set_rules('company', _l('client_company'), 'required');
        $this->form_validation->set_rules('tenants_name', _l('tenants_name'), 'required|alpha_numeric|is_unique[' . db_prefix() . 'client_plan.tenants_name]');

        if (false === $this->form_validation->run()) {
            $validationErrors = [];

            foreach ($postData as $field => $value) {
                if (!empty(form_error($field))) {
                    $validationErrors[$field] = strip_tags(form_error($field));
                }
            }

            $this->response(['message' => $validationErrors], 422);
        }

        $this->load->model('clients_model');

        $countryId = !empty($postData['country']) && is_numeric($postData['country']) ? $postData['country'] : 0;

        if (is_automatic_calling_codes_enabled() && $countryId != 0) {
            $customerCountry = get_country($countryId);

            if ($customerCountry) {
                $callingCode = '+' . ltrim($customerCountry->calling_code, '+');

                if (startsWith($postData['contact_phonenumber'], $customerCountry->calling_code)) { // with calling code but without the + prefix
                    $postData['contact_phonenumber'] = '+' . $postData['contact_phonenumber'];
                } elseif (!startsWith($postData['contact_phonenumber'], $callingCode)) {
                    $postData['contact_phonenumber'] = $callingCode . $postData['contact_phonenumber'];
                }
            }
        }

        if ('1' == get_option('email_verification_require_after_tenant_register')) {
            define('CONTACT_REGISTERING', true);
        }

        $data = [
            'is_primary'          => '1',
            'billing_street'      => $postData['address'] ?? '',
            'billing_city'        => $postData['city'] ?? '',
            'billing_state'       => $postData['state'] ?? '',
            'billing_zip'         => $postData['zip'] ?? '',
            'billing_country'     => $countryId,
            'firstname'           => $postData['firstname'],
            'lastname'            => $postData['lastname'],
            'email'               => $postData['email'],
            'contact_phonenumber' => $postData['contact_phonenumber'] ?? '',
            'website'             => $postData['website'] ?? '',
            'title'               => $postData['title'] ?? '',
            'password'            => $postData['password'],
            'company'             => $postData['company'] ?? '',
            'vat'                 => $postData['vat'] ?? '',
            'phonenumber'         => $postData['phonenumber'] ?? '',
            'country'             => $postData['country'] ?? '',
            'city'                => $postData['city'] ?? '',
            'address'             => $postData['address'] ?? '',
            'zip'                 => $postData['zip'] ?? '',
            'state'               => $postData['state'] ?? '',
            'custom_fields'       => isset($postData['custom_fields']) && is_array($postData['custom_fields']) ? $postData['custom_fields'] : [],
            'default_language'    => ('' != get_contact_language()) ? get_contact_language() : get_option('active_language'),
        ];

        $planid = $postData['planid'];
        $planData = $this->custom_model->getSingleRow(db_prefix() . 'plan_management', ['id' => $planid], 'array');
        if (!$planData) {
            $this->response(['message' => _l('plan_not_available')], 404);
        }

        $clientid = $this->clients_model->add($data, true);

        if ($clientid) {
            $contactId   = get_primary_contact_user_id($clientid);

            if ($contactId) {
                $data = [
                    'contactid'    => $contactId,
                    'tenant_plan'  => $planid,
                    'tenants_name' => $postData['tenants_name'],
                ];
                $this->superadmin_lib->assignPlanToClientAndInstall($data, $clientid);
            }

            if ('1' == get_option('customers_register_require_confirmation')) {
                send_customer_registered_email_to_administrators($clientid);

                $this->customers_model->require_confirmation($clientid);
                $this->response(['message' => _l('customer_register_account_confirmation_approval_notice')], 200);
            }

            if ('1' == get_option('email_verification_require_after_tenant_register')) {
                $this->response(['message' => _l('email_has_been_sent_to_registered_email_address')], 200);
            }

            $this->response(['message' => _l('tenant_successfully_registered')], 200);
        }

        $this->response(['message' => _l('something_went_wrong')], 400);
    }
}
