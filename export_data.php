<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set("memory_limit", "2G");

error_reporting(E_ALL);

include '../app/Mage.php';
Mage::app('admin');



class Customers{

    protected $customer_ids;
    protected $customer_ids1;
    protected $customer_ids2;
    protected $customer_ids3;


    protected $last_pay;
    protected $sub_status;
    protected $event_start_date;


    protected $customer_login_DateFrom;
    protected $customer_login_DateTo;

    public function __construct($params)
    {
        $this->last_pay = $params['last_pay'];
        $this->sub_status = $params['sub_status'];
        $this->event_start_date = $params['event_start_date'];//'2019-12-01'

        $this->customer_login_DateFrom = $params['customer_login_DateFrom'];//'2019-10-15'
        $this->customer_login_DateTo = $params['customer_login_DateTo'];//'2019-11-30'
    }

    /**
     * vip
     * @param string $fromDate
     * @param string $sub_status
     * @return null
     */
    protected function getCustomers1()
    {
        /*** Students enrolled in classes after Dec 1, 2019 (RWT estimated this at 1,000 users) ***/

        $sql = "SELECT * from rwt_subscribers 
                WHERE last_pay >='{$this->last_pay}' AND sub_status = '{$this->sub_status}'";

        /** @var  $connection Mage_Core_Model_Resource_Resource */
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');

        $items = $read->fetchAll($sql);
        $result = null;
        foreach ($items as $item) {
            $id = $item['subscription_id'];
            $result[$id] = $item;

        }
        return $result;
    }

    /**
     * Students
     * @param string $fromDate
     * @return null
     */
    protected function getCustomers2()
    {
        /*** Students enrolled in classes after Dec 1, 2019 (RWT estimated this at 1,000 users) ***/

        $sql = "select o.customer_id, e.product_id, e.start_date, e.end_date, e.status, ae.order_id, ae.order_item_id, a.email, a.phone, e.is_active 
                from cc_attendee_event ae
                join cc_event e on ae.event_id = e.event_id
                join cc_attendee a on a.attendee_id = ae.attendee_id
                join sales_flat_order o on o.entity_id = ae.order_id
                where e.start_date > '{$this->event_start_date}'";


        /** @var  $connection Mage_Core_Model_Resource_Resource */
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');

        $items = $read->fetchAll($sql);
        $result = null;
        foreach ($items as $item) {
            $id = $item['order_item_id'];
            $result[$id] = $item;
        }
        return $result;
    }

    /**
     * customer3
     * @param string $logDateFrom
     * @param string $logDateTo
     * @return null
     */
    protected function getCustomers3()
    {
        /*** Students enrolled in classes after Dec 1, 2019 (RWT estimated this at 1,000 users) ***/

        $sql = "SELECT ce.*
                FROM (SELECT customer_id, MAX(login_at) AS login_last
                FROM log_customer
                GROUP BY  customer_id
                HAVING (login_last > '{$this->customer_login_DateFrom}' AND login_last < '{$this->customer_login_DateTo}') )  cl
                JOIN customer_entity ce ON cl.customer_id=ce.entity_id
                WHERE ce.created_at != cl.login_last";

        /** @var  $connection Mage_Core_Model_Resource_Resource */
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');

        $items = $read->fetchAll($sql);
        $result = null;
        foreach ($items as $item) {
            $id = $item['entity_id'];
            $result[$id] = $item;

        }
        return $result;
    }


    public function getCustomerIds1()
    {
        if(!$this->customer_ids1) {
            $customer_ids = $this->getCustomers1();
            if($customer_ids){
                $this->customer_ids1 = array_column($customer_ids, 'customer_id');
            }
        }
        return $this->customer_ids1;
    }

    public function getCustomerIds2()
    {
        if(!$this->customer_ids2) {
            $customer_ids = $this->getCustomers2();
            if($customer_ids){
                $this->customer_ids2 = array_column($customer_ids, 'customer_id');
            }
        }
        return $this->customer_ids2;
    }

    public function getCustomerIds3()
    {
        if(!$this->customer_ids3) {
            $customer_ids = $this->getCustomers3();
            if($customer_ids){
                $this->customer_ids3 = array_column($customer_ids, 'entity_id');
            }
        }
        return $this->customer_ids3;
    }


    /**
     * @return array
     */
    public function getCustomerIds()
    {
        if(!$this->customer_ids) {

            $custIds1 = $this->getCustomerIds1();
            $custIds2 = $this->getCustomerIds2();
            $custIds3 = $this->getCustomerIds3();

            if($custIds1 || $custIds2 || $custIds3) {
                $this->customer_ids = array_unique(array_merge($custIds1, $custIds2, $custIds3));

            }

        }
        return $this->customer_ids;
    }

}


abstract class Base {

    protected $map;
    protected $data;
    protected $filename;


    /** @var Customers */
    protected $customers;


    /**
     * @return Customers
     */
    protected function getCustomers()
    {
        return $this->customers;
    }

    public function __construct($params)
    {
        $this->customers = $params['customers'];
    }

    /*** files ***/

    protected function saveCsv($filename, array $data)
    {
        $csv = new Varien_File_Csv();
        $csv->setDelimiter(',');
        $csv->setEnclosure('"');

        try
        {
            $csv->saveData($filename, $data);
            print_r("saved file {$filename}<br>");
        }catch (Exception $e) {
            Mage::logException($e);
        }

        return $this;
    }


    /*** abstracts ***/

    abstract public function load();

    abstract public function save();
}


class UserData extends Base {


    protected $map = [
        'Username'=>'email',
        'Email address'=>'email',
        'First Name'=>'firstname',
        'Middle Name'=>'middlename',
        'Last Name'=>'lastname',
        'Billing First Name'=>'billing_firstname',
        'Billing Last Name'=>'billing_lastname',
        'Billing Company'=>'billing_company',
        'Billing Address Line 1'=>'billing_street1',
        'Billing Address Line 2'=>'billing_street2',
        'City'=>'billing_city',
        'Zip Code'=>'billing_postcode',
        'Country'=>'billing_country_id',
        'State'=>'billing_region',
        'Phone'=>'billing_telephone',
        'VIP'=>'vip',
        'course_access'=>'course_access',
    ];

    public function __construct($params)
    {
        $this->filename = $params['filename'];
        parent::__construct($params);
    }

    protected function getCustomrCollection()
    {
        $customerIds = $this->getCustomers()->getCustomerIds();

        /** @var Mage_Customer_Model_Resource_Customer_Collection $collection */
        $collection = Mage::getModel('customer/customer')->getCollection()
            ->addAttributeToSelect('*')
            ->addFieldToFilter('entity_id', ['in'=>$customerIds])
//            ->addAttributeToFilter('is_active', 1)
            ->joinAttribute('billing_postcode', 'customer_address/postcode', 'default_billing', null, 'left')
            ->joinAttribute('billing_city', 'customer_address/city', 'default_billing', null, 'left')
            ->joinAttribute('billing_region', 'customer_address/region', 'default_billing', null, 'left')
            ->joinAttribute('billing_country_id', 'customer_address/country_id', 'default_billing', null, 'left')
            ->joinAttribute('billing_telephone', 'customer_address/telephone', 'default_billing', null, 'left')
            ->joinAttribute('billing_company', 'customer_address/company', 'default_billing', null, 'left')
            ->joinAttribute('billing_firstname', 'customer_address/firstname', 'default_billing', null, 'left')
            ->joinAttribute('billing_lastname', 'customer_address/lastname', 'default_billing', null, 'left')
            ->joinAttribute('billing_street', 'customer_address/street', 'default_billing', null, 'left')
        ;

        return $collection;
    }

    public function load()
    {
        $collection = $this->getCustomrCollection();

        $row = 1;
        foreach ($collection as $item) {
            $col = -1;

            foreach ($this->map as $fieldName=>$code) {

                $value = null;

                if($code == 'billing_street1') {
                    $valueArr = explode("\n", $item->getData('billing_street'));
                    if(key_exists(0, $valueArr)) {
                        $value = $valueArr[0];
                    }
                }
                elseif($code == 'billing_street2') {
                    $valueArr = explode("\n", $item->getData('billing_street'));
                    if(key_exists(1, $valueArr)) {
                        $value = $valueArr[1];
                    }
                }
                elseif($code == 'vip') {
                    $value = in_array($item->getId(), $this->getCustomers()->getCustomerIds1()) ? "1" : "0";
                }
                elseif($code == 'course_access') {
                    $value = in_array($item->getId(), $this->getCustomers()->getCustomerIds3()) ? "1" : "0";
                }
                elseif( $code ) {
                    $value = $item->getData($code);
                }

                $data[$row][$col++] = $value;
            }
            $row++;
        }


        $data[0] = array_keys($this->map);

        $this->data = $data;

        return $this;
    }

    public function save()
    {
        if($this->data) {
            $this->saveCsv($this->filename, $this->data);
        }

        return $this;
    }
}


class Subscriptions extends Base  {


    protected $map = [
        'Email address'=>'customer_id',//vip
        'Subscription SKU'=>'sub_sku',//vip
        'Last payment date'=>'last_pay',//vip
        'Next payment date'=>'next_pay_date',//vip
        'Subscription profile ID with Authorize.net'=>'cim_payment_profile',//vip
        'User profile with Authorize.net'=>'cim_customer_profile',//vip
        'Next renewal date'=>'recycle_day',

        'Last payment amount'=>'amount_paid',//order->payment

        'Subscription start date'=>'signup_date',//event
        'Subscription last paid'=>'last_pay',//event

        'Credit card last 4 digits' => 'cc_last4',
        'Credit card Type' => 'cc_type',
        'Credit card expiry year' => 'cc_exp_year',
        'Credit card expiry month' => 'cc_exp_month',
    ];


    protected $last_pay;// '2019-10-01';
    protected $sub_status;// 'current';
    protected $product_attribute_set_id = 16;

    public function __construct($params)
    {
        $this->filename = $params['filename'];
        $this->last_pay = $params['last_pay'];
        $this->sub_status = $params['sub_status'];

        parent::__construct($params);
    }



    /**
     * vip
     * @param string $fromDate
     * @param string $sub_status
     * @return null
     */
    protected function getVipSubscription()
    {
        /*** Students enrolled in classes after Dec 1, 2019 (RWT estimated this at 1,000 users) ***/

        $sql = "SELECT * from rwt_subscribers 
                WHERE last_pay >='{$this->last_pay}' AND sub_status = '{$this->sub_status}'";

        /** @var  $connection Mage_Core_Model_Resource_Resource */
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');

        $items = $read->fetchAll($sql);


        $result = new Varien_Data_Collection();
        foreach ($items as $item) {
            $item['id']= $item['subscription_id'];
            $result->addItem(new Varien_Object($item));
        }

        return $result;
    }

    public function load()
    {

        $collection = $this->getVipSubscription();

        $data[0] = array_keys($this->map);
        $row = 1;
        foreach ($collection as $item) {

            $col = 0;

            $incrementId = $item->getLastOrderId();

            /** @var Mage_Sales_Model_Order $order */
            $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);

            if(empty($order->getData())) {
                print_r("last_order_id '{$incrementId}' is not exist in Subscription {$item->getId()}<br>");
            }

            foreach ($this->map as $fieldName=>$code) {

                $value = null;
                if(in_array($code, ['amount_paid','cc_last4', 'cc_type', 'cc_exp_month', 'cc_exp_year'] )) {
                    $value = $order->getPayment()->getData($code);
                }
                elseif($code == 'customer_id') {
                    $value = $item->getData($code);

                    if(!in_array($value, $this->getCustomers()->getCustomerIds())) {
                        $value = $order->getData('customer_email');
                    }
                }
                else{
                    $value = $item->getData($code);
                }
                $data[$row][$col++] = $value;
            }

            $row++;
        }

        $this->data = $data;

        return $this;
    }

    public function save()
    {
        if($this->data) {
            $this->saveCsv($this->filename, $this->data);
        }

        return $this;
    }
}



/*** RUNUNG CODE ***/


$customers = new Customers([
    'last_pay'=>'2019-10-01',
    'sub_status'=>'current',
    'event_start_date' => '2019-12-01',

    'customer_login_DateFrom' => '2019-10-15',
    'customer_login_DateTo' => '2019-11-30'
]);


$userData = new UserData([
        'filename' => 'user_data.csv',
        'customers' => $customers,
    ]
);
$subscriptions = new Subscriptions([
        'filename' => 'subscriptions.csv',
        'customers' => $customers,

        'last_pay' => '2019-10-01',
        'sub_status' => 'current',
    ]
);

$userData->load()->save();
$subscriptions->load()->save();
