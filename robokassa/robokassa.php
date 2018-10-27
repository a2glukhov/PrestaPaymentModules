<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Robokassa extends PaymentModule
{
    //const FLAG_DISPLAY_PAYMENT_INVITE = 'BANK_WIRE_PAYMENT_INVITE';

    const FLAG_ROBOKASSA_DEMO = 'ROBOKASSA_DEMO';
    const FLAG_ROBOKASSA_POSTVALIDATE = 'ROBOKASSA_POSTVALIDATE';

    protected $_html = '';
    protected $_postErrors = array();

    public $login;
    public $password1;
    public $password2;
    public $demo;
    public $postValidate;

    public $resultUrl;
    public $successUrl;
    public $failUrl;

    // public $details;
    // public $address;
    //public $extra_mail_vars;

    public function __construct()
    {
        $this->name = 'robokassa';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = array('min' => '1.7.1.0', 'max' => _PS_VERSION_);
        $this->author = 'PrestaShop';
        $this->controllers = array('payment', 'validation');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $config = Configuration::getMultiple(array('ROBOKASSA_LOGIN', 'ROBOKASSA_PASSWORD1', 'ROBOKASSA_PASSWORD2'));
        if (!empty($config['ROBOKASSA_LOGIN'])) {
            $this->login = $config['ROBOKASSA_LOGIN'];
        }
        if (!empty($config['ROBOKASSA_PASSWORD1'])) {
            $this->password1 = $config['ROBOKASSA_PASSWORD1'];
        }
        if (!empty($config['ROBOKASSA_PASSWORD2'])) {
            $this->password2 = $config['ROBOKASSA_PASSWORD2'];
        }
        // if (!empty($config['BANK_WIRE_RESERVATION_DAYS'])) {
        //     $this->reservation_days = $config['BANK_WIRE_RESERVATION_DAYS'];
        // }

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('Robokassa payment', array(), 'Modules.Robokassa.Admin');
        $this->description = $this->trans('Service to receive payments by plastic cards, in every e-currency, using mobile commerce.', array(), 'Modules.Robokassa.Admin');

        $this->confirmUninstall = $this->trans('Are you sure about removing these details?', array(), 'Modules.Robokassa.Admin');
        if (!isset($this->login) || !isset($this->password1) || !isset($this->password2)) {
            $this->warning = $this->trans('Robokassa login, password1, password2 must be configured before using this module.', array(), 'Modules.Robokassa.Admin');
        }
        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->trans('No currency has been set for this module.', array(), 'Modules.Robokassa.Admin');
        }

        // $this->extra_mail_vars = array(
        //                                 '{robokassa_login}' => Configuration::get('ROBOKASSA_LOGIN'),
        //                                 '{robokassa_password1}' => Configuration::get('ROBOKASSA_PASSWORD1'),
        //                                 '{robokassa_password2}' => Configuration::get('ROBOKASSA_PASSWORD2'),
        //                                 );
    }

    public function install()
    {
        Configuration::updateValue(self::FLAG_ROBOKASSA_DEMO, true);
        Configuration::updateValue(self::FLAG_ROBOKASSA_POSTVALIDATE, true);
        if (!parent::install() || !$this->registerHook('paymentReturn') || !$this->registerHook('paymentOptions')) {
            return false;
        }
        return true;
    }

    public function uninstall()
    {
        // $languages = Language::getLanguages(false);
        // foreach ($languages as $lang) {
        //     if (!Configuration::deleteByName('BANK_WIRE_CUSTOM_TEXT', $lang['id_lang'])) {
        //         return false;
        //     }
        // }

        if (!Configuration::deleteByName('ROBOKASSA_LOGIN')
                || !Configuration::deleteByName('ROBOKASSA_PASSWORD1')
                || !Configuration::deleteByName('ROBOKASSA_PASSWORD2')
                || !Configuration::deleteByName(self::FLAG_ROBOKASSA_DEMO)
                || !Configuration::deleteByName(self::FLAG_ROBOKASSA_POSTVALIDATE)
                || !parent::uninstall()) {
            return false;
        }
        return true;
    }

    protected function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue(self::FLAG_ROBOKASSA_DEMO,
                Tools::getValue(self::FLAG_ROBOKASSA_DEMO));

            Configuration::updateValue(self::FLAG_ROBOKASSA_POSTVALIDATE,
                Tools::getValue(self::FLAG_ROBOKASSA_POSTVALIDATE));

            if (!Tools::getValue('ROBOKASSA_PASSWORD1')) {
                $this->_postErrors[] = $this->trans('Robokassa password1 is required.', array(), 'Modules.Robokassa.Admin');
            } elseif (!Tools::getValue('ROBOKASSA_LOGIN')) {
                $this->_postErrors[] = $this->trans('Robokassa login is required.', array(), "Modules.Robokassa.Admin");
            } elseif (!Tools::getValue('ROBOKASSA_PASSWORD2')) {
                $this->_postErrors[] = $this->trans('Robokassa password2 is required.', array(), "Modules.Robokassa.Admin");
            }
        }
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('ROBOKASSA_LOGIN', Tools::getValue('ROBOKASSA_LOGIN'));
            Configuration::updateValue('ROBOKASSA_PASSWORD1', Tools::getValue('ROBOKASSA_PASSWORD1'));
            Configuration::updateValue('ROBOKASSA_PASSWORD2', Tools::getValue('ROBOKASSA_PASSWORD2'));

            // $custom_text = array();
            // $languages = Language::getLanguages(false);
            // foreach ($languages as $lang) {
            //     if (Tools::getIsset('BANK_WIRE_CUSTOM_TEXT_'.$lang['id_lang'])) {
            //         $custom_text[$lang['id_lang']] = Tools::getValue('BANK_WIRE_CUSTOM_TEXT_'.$lang['id_lang']);
            //     }
            // }
            // Configuration::updateValue('BANK_WIRE_RESERVATION_DAYS', Tools::getValue('BANK_WIRE_RESERVATION_DAYS'));
            // Configuration::updateValue('BANK_WIRE_CUSTOM_TEXT', $custom_text);
        }
        $this->_html .= $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Global'));
    }

    protected function _displayRobokassa()
    {
        return $this->display(__FILE__, 'infos.tpl');
    }

    public function getContent()
    {
        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        } else {
            $this->_html .= '<br />';
        }

        $this->_html .= $this->_displayRobokassa();
        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $this->smarty->assign(
            $this->getTemplateVarInfos()
        );

        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name)
                ->setCallToActionText($this->trans('Pay via robokassa', array(), 'Modules.Robokassa.Shop'))
                ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                ->setAdditionalInformation($this->fetch('module:robokassa/views/templates/hook/robokassa_intro.tpl'));
        $payment_options = [
            $newOption,
        ];

        return $payment_options;
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active || !Configuration::get(self::FLAG_DISPLAY_PAYMENT_INVITE)) {
            return;
        }

        $state = $params['order']->getCurrentState();
        if (
            in_array(
                $state,
                array(
                    Configuration::get('PS_OS_ROBOKASSA'),
                    Configuration::get('PS_OS_OUTOFSTOCK'),
                    Configuration::get('PS_OS_OUTOFSTOCK_UNPAID'),
                )
        )) {
            $bankwireOwner = $this->login;
            if (!$bankwireOwner) {
                $bankwireOwner = '___________';
            }

            $bankwireDetails = Tools::nl2br($this->details);
            if (!$bankwireDetails) {
                $bankwireDetails = '___________';
            }

            $bankwireAddress = Tools::nl2br($this->address);
            if (!$bankwireAddress) {
                $bankwireAddress = '___________';
            }

            $this->smarty->assign(array(
                'shop_name' => $this->context->shop->name,
                'total' => Tools::displayPrice(
                    $params['order']->getOrdersTotalPaid(),
                    new Currency($params['order']->id_currency),
                    false
                ),
                'bankwireDetails' => $bankwireDetails,
                'bankwireAddress' => $bankwireAddress,
                'bankwireOwner' => $bankwireOwner,
                'status' => 'ok',
                'reference' => $params['order']->reference,
                'contact_url' => $this->context->link->getPageLink('contact', true)
            ));
        } else {
            $this->smarty->assign(
                array(
                    'status' => 'failed',
                    'contact_url' => $this->context->link->getPageLink('contact', true),
                )
            );
        }

        return $this->fetch('module:robokassa/views/templates/hook/payment_return.tpl');
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('Robokassa settings', array(), 'Modules.Robokassa.Admin'),
                    'icon' => 'icon-envelope'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Robokassa login', array(), 'Modules.Robokassa.Admin'),
                        'name' => 'ROBOKASSA_LOGIN',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Robokassa password1', array(), 'Modules.Robokassa.Admin'),
                        'name' => 'ROBOKASSA_PASSWORD1',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Robokassa password2', array(), 'Modules.Robokassa.Admin'),
                        'name' => 'ROBOKASSA_PASSWORD2',
                        'required' => true
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->trans('Display the invitation to pay in the order confirmation page', array(), 'Modules.Robokassa.Admin'),
                        'name' => self::FLAG_ROBOKASSA_DEMO,
                        'is_bool' => true,
                        'hint' => $this->trans('Your country\'s legislation may require you to send the invitation to pay by email only. Disabling the option will hide the invitation on the confirmation page.', array(), 'Modules.Robokassa.Admin'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->trans('Enabled', array(), 'Admin.Global'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->trans('Disabled', array(), 'Admin.Global'),
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->trans('Display the invitation to pay in the order confirmation page', array(), 'Modules.Robokassa.Admin'),
                        'name' => self::FLAG_ROBOKASSA_POSTVALIDATE,
                        'is_bool' => true,
                        'hint' => $this->trans('Your country\'s legislation may require you to send the invitation to pay by email only. Disabling the option will hide the invitation on the confirmation page.', array(), 'Modules.Robokassa.Admin'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->trans('Enabled', array(), 'Admin.Global'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->trans('Disabled', array(), 'Admin.Global'),
                            )
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                )
            ),
        );
        // $fields_form_customization = array(
        //     'form' => array(
        //         'legend' => array(
        //             'title' => $this->trans('Customization', array(), 'Modules.Robokassa.Admin'),
        //             'icon' => 'icon-cogs'
        //         ),
        //         'input' => array(
        //             array(
        //                 'type' => 'text',
        //                 'label' => $this->trans('Reservation period', array(), 'Modules.Robokassa.Admin'),
        //                 'desc' => $this->trans('Number of days the items remain reserved', array(), 'Modules.Robokassa.Admin'),
        //                 'name' => 'BANK_WIRE_RESERVATION_DAYS',
        //             ),
        //             array(
        //                 'type' => 'textarea',
        //                 'label' => $this->trans('Information to the customer', array(), 'Modules.Robokassa.Admin'),
        //                 'name' => 'BANK_WIRE_CUSTOM_TEXT',
        //                 'desc' => $this->trans('Information on the bank transfer (processing time, starting of the shipping...)', array(), 'Modules.Robokassa.Admin'),
        //                 'lang' => true
        //             ),
        //             array(
        //                 'type' => 'switch',
        //                 'label' => $this->trans('Display the invitation to pay in the order confirmation page', array(), 'Modules.Robokassa.Admin'),
        //                 'name' => self::FLAG_DISPLAY_PAYMENT_INVITE,
        //                 'is_bool' => true,
        //                 'hint' => $this->trans('Your country\'s legislation may require you to send the invitation to pay by email only. Disabling the option will hide the invitation on the confirmation page.', array(), 'Modules.Robokassa.Admin'),
        //                 'values' => array(
        //                     array(
        //                         'id' => 'active_on',
        //                         'value' => true,
        //                         'label' => $this->trans('Enabled', array(), 'Admin.Global'),
        //                     ),
        //                     array(
        //                         'id' => 'active_off',
        //                         'value' => false,
        //                         'label' => $this->trans('Disabled', array(), 'Admin.Global'),
        //                     )
        //                 ),
        //             ),
        //         ),
        //         'submit' => array(
        //             'title' => $this->trans('Save', array(), 'Admin.Actions'),
        //         )
        //     ),
        // );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? : 0;
        $this->fields_form = array();
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='
            .$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        //return $helper->generateForm(array($fields_form, $fields_form_customization));
        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        // $custom_text = array();
        // $languages = Language::getLanguages(false);
        // foreach ($languages as $lang) {
        //     $custom_text[$lang['id_lang']] = Tools::getValue(
        //         'BANK_WIRE_CUSTOM_TEXT_'.$lang['id_lang'],
        //         Configuration::get('BANK_WIRE_CUSTOM_TEXT', $lang['id_lang'])
        //     );
        // }

        return array(
            'ROBOKASSA_LOGIN' => Tools::getValue('ROBOKASSA_LOGIN', Configuration::get('ROBOKASSA_LOGIN')),
            'ROBOKASSA_PASSWORD1' => Tools::getValue('ROBOKASSA_PASSWORD1', Configuration::get('ROBOKASSA_PASSWORD1')),
            'ROBOKASSA_PASSWORD2' => Tools::getValue('ROBOKASSA_PASSWORD2', Configuration::get('ROBOKASSA_PASSWORD2')),
            // 'BANK_WIRE_RESERVATION_DAYS' => Tools::getValue('BANK_WIRE_RESERVATION_DAYS', Configuration::get('BANK_WIRE_RESERVATION_DAYS')),
            // 'BANK_WIRE_CUSTOM_TEXT' => $custom_text,
            self::FLAG_ROBOKASSA_DEMO => Tools::getValue(self::FLAG_ROBOKASSA_DEMO,
                Configuration::get(self::FLAG_ROBOKASSA_DEMO)),
            self::FLAG_ROBOKASSA_POSTVALIDATE => Tools::getValue(self::FLAG_ROBOKASSA_POSTVALIDATE,
                Configuration::get(self::FLAG_ROBOKASSA_POSTVALIDATE))
        );
    }

    public function getTemplateVarInfos()
    {
        $cart = $this->context->cart;
        $total = sprintf(
            $this->trans('%1$s (tax incl.)', array(), 'Modules.Robokassa.Shop'),
            Tools::displayPrice($cart->getOrderTotal(true, Cart::BOTH))
        );

         $bankwireOwner = $this->login;
        if (!$bankwireOwner) {
            $bankwireOwner = '___________';
        }

        $bankwireDetails = Tools::nl2br($this->details);
        if (!$bankwireDetails) {
            $bankwireDetails = '___________';
        }

        $bankwireAddress = Tools::nl2br($this->address);
        if (!$bankwireAddress) {
            $bankwireAddress = '___________';
        }

        $bankwireReservationDays = Configuration::get('BANK_WIRE_RESERVATION_DAYS');
        if (false === $bankwireReservationDays) {
            $bankwireReservationDays = 7;
        }

        $bankwireCustomText = Tools::nl2br(Configuration::get('BANK_WIRE_CUSTOM_TEXT', $this->context->language->id));
        if (false === $bankwireCustomText) {
            $bankwireCustomText = '';
        }

        return array(
            'total' => $total,
            'bankwireDetails' => $bankwireDetails,
            'bankwireAddress' => $bankwireAddress,
            'bankwireOwner' => $bankwireOwner,
            'bankwireReservationDays' => (int)$bankwireReservationDays,
            'bankwireCustomText' => $bankwireCustomText,
        );
    }
}
