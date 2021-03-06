<?php

namespace App\Http\Controllers\Front;

use App\ApiKey;
use App\Http\Controllers\Common\MailChimpController;
use App\Http\Controllers\Common\TemplateController;
use App\Model\Common\Setting;
use App\Model\Common\State;
use App\Model\Common\Template;
use App\Model\Order\Invoice;
use App\Model\Order\InvoiceItem;
use App\Model\Order\Order;
use App\Model\Payment\Plan;
use App\Model\Product\Price;
use App\Model\Product\Product;
use App\Model\Product\Subscription;
use App\User;
use Bugsnag;
use Cart;
use Illuminate\Http\Request;

class CheckoutController extends InfoController
{
    public $subscription;
    public $plan;
    public $templateController;
    public $product;
    public $price;
    public $user;
    public $setting;
    public $template;
    public $order;
    public $addon;
    public $invoice;
    public $invoiceItem;
    public $mailchimp;

    public function __construct()
    {
        $subscription = new Subscription();
        $this->subscription = $subscription;

        $plan = new Plan();
        $this->plan = $plan;

        $templateController = new TemplateController();
        $this->templateController = $templateController;

        $product = new Product();
        $this->product = $product;

        $price = new Price();
        $this->price = $price;

        $user = new User();
        $this->user = $user;

        $setting = new Setting();
        $this->setting = $setting;

        $template = new Template();
        $this->template = $template;

        $order = new Order();
        $this->order = $order;

        $invoice = new Invoice();
        $this->invoice = $invoice;

        $invoiceItem = new InvoiceItem();
        $this->invoiceItem = $invoiceItem;

        // $mailchimp = new MailChimpController();
        // $this->mailchimp = $mailchimp;
    }

    public function checkoutForm(Request $request)
    {
        $currency = 'INR';
        $cart_currency = 'INR';
        if (!\Auth::user()) {
            $url = $request->segments();
            $content = Cart::getContent();

            \Session::put('session-url', $url[0]);
            $domain = $request->input('domain');
            if ($domain) {
                foreach ($domain as $key => $value) {
                    \Session::put('domain'.$key, $value);
                }
            }
            \Session::put('content', $content);

            return redirect('auth/login')->with('fails', 'Please login');
        }

        if (\Session::has('items')) {
            $content = \Session::get('items');
            $attributes = $this->getAttributes($content);
        } else {
            $content = Cart::getContent();
            $attributes = $this->getAttributes($content);
        }

        $require = [];

        //        if ($content->count() == 0) {
        //            return redirect('home');
        //        }
        if ($cart_currency != $currency) {
            return redirect('checkout');
        }
        if (count($require) > 0) {
            $this->validate($request, [
                'domain.*' => 'required',
                    ], [
                'domain.*.required' => 'Please provide Domain name',
                //'domain.*.url'      => 'Domain name is not valid',
                       ]);
        }

        try {
            $domain = $request->input('domain');

            if ($domain) {
                foreach ($domain as $key => $value) {
                    \Session::put('domain'.$key, $value);
                }
            }

            return view('themes.default1.front.checkout', compact('content', 'attributes'));
        } catch (\Exception $ex) {
            app('log')->error($ex->getMessage());
            Bugsnag::notifyException($ex);

            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    public function getAttributes($content)
    {
        try {
            $attributes = [];
            foreach ($content as $key => $item) {
                $attributes[] = $item->attributes;
                $cart_currency = $attributes[0]['currency'][0]['code'];
                $user_currency = \Auth::user()->currency;
                $currency = 'INR';
                if ($user_currency == 1 || $user_currency == 'USD') {
                    $currency = 'USD';
                }
                if ($cart_currency != $currency) {
                    $id = $item->id;
                    Cart::remove($id);
                    $controller = new CartController();
                    $items = $controller->addProduct($id);
                    Cart::add($items);
                    //
                }

                $require_domain = $this->product->where('id', $item->id)->first()->require_domain;
                $require = [];
                if ($require_domain == 1) {
                    $require[$key] = $item->id;
                }

                return $attributes;
                //$attributes[] = $item->attributes;
            }
        } catch (\Exception $ex) {
            app('log')->error($ex->getMessage());
            Bugsnag::notifyException($ex);

            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    public function payNow($invoiceid)
    {
        try {
            $invoice = $this->invoice->find($invoiceid);
            // dd($invoice);
            $items = new \Illuminate\Support\Collection();
            // dd($items);
            if ($invoice) {
                $items = $invoice->invoiceItem()->get();

                $product = $this->product($invoiceid);
            }

            return view('themes.default1.front.paynow', compact('invoice', 'items', 'product'));
        } catch (\Exception $ex) {
            app('log')->error($ex->getMessage());
            Bugsnag::notifyException($ex);

            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    public function postCheckout(Request $request)
    {
        $invoice_controller = new \App\Http\Controllers\Order\InvoiceController();
        $info_cont = new \App\Http\Controllers\Front\InfoController();
        $payment_method = $request->input('payment_gateway');
        $paynow = false;
        if ($request->input('invoice_id')) {
            $paynow = true;
        }
        $cost = $request->input('cost');
        $state = $this->getState();

        try {
            if ($paynow === false) {
                /*
                 * Do order, invoicing etc
                 */
                $invoice = $invoice_controller->generateInvoice();

                $pay = $this->payment($payment_method, $status = 'pending');
                $payment_method = $pay['payment'];
                $status = $pay['status'];
                $invoice_no = $invoice->number;
                $date = $this->getDate($invoice);
                $invoiceid = $invoice->id;
                $amount = $invoice->grand_total;
                $url = '';
                $cart = Cart::getContent();
                $invoices = $this->invoice->find($invoiceid);
                $items = new \Illuminate\Support\Collection();
                if ($invoices) {
                    $items = $invoice->invoiceItem()->get();
                    $product = $this->product($invoiceid);
                    $content = Cart::getContent();
                    $attributes = $this->getAttributes($content);
                }
            } else {
                $items = new \Illuminate\Support\Collection();
                $invoiceid = $request->input('invoice_id');
                $invoice = $this->invoice->find($invoiceid);
                $date = $this->getDate($invoice);
                $items = $invoice->invoiceItem()->get();
                $product = $this->product($invoiceid);
                $amount = $invoice->grand_total;
                $content = Cart::getContent();
                $attributes = $this->getAttributes($content);
            }
            if (Cart::getSubTotal() != 0 || $cost > 0) {
                $rzp_key = ApiKey::where('id', 1)->value('rzp_key');
                $rzp_secret = ApiKey::where('id', 1)->value('rzp_secret');
                $apilayer_key = ApiKey::where('id', 1)->value('apilayer_key');

                return view('themes.default1.front.postCheckout',
                    compact('amount', 'invoice_no', ' invoiceid', ' payment_method',
                        'phone', 'invoice', 'items', 'product', 'paynow', 'attributes',
                        'rzp_key', 'rzp_secret', 'apilayer_key'));
            } else {
                $action = $this->checkoutAction($invoice);
                $check_product_category = $this->product($invoiceid);

                //Update Subscriber To Mailchimp
                $mailchimp = new \App\Http\Controllers\Common\MailChimpController();
                $r = $mailchimp->updateSubscriberForFreeProduct(\Auth::user()->email, $check_product_category->id);
                $url = '';
                if ($check_product_category->category) {
                    $url = view('themes.default1.front.postCheckoutTemplate', compact('invoice','date',
                        'product', 'items', 'attributes', 'state'))->render();
                }
                \Cart::clear();

                return redirect()->back()->with('success', $url);
            }
        } catch (\Exception $ex) {
            app('log')->error($ex->getMessage());
            Bugsnag::notifyException($ex);

            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    public function checkoutAction($invoice)
    {
        try {
            //get elements from invoice
            $invoice_number = $invoice->number;
            $invoice_id = $invoice->id;
            $invoice->status = 'success';
            $invoice->save();
            $invoice_items = $this->invoiceItem->where('invoice_id', $invoice->id)->first();
            $product = $invoice_items->product_name;

            $user_id = \Auth::user()->id;

            $url = '';
            $check_product_category = $this->product($invoice_id);
            if ($check_product_category->category) {
                $url = url("download/$user_id/$invoice_number");
                //execute the order
                $order = new \App\Http\Controllers\Order\OrderController();
                $order->executeOrder($invoice->id, $order_status = 'executed');
                $payment = new \App\Http\Controllers\Order\InvoiceController();
                $payment->postRazorpayPayment($invoice_id, $invoice->grand_total);
            }

            return 'success';
        } catch (\Exception $ex) {
            app('log')->error($ex->getMessage());
            Bugsnag::notifyException($ex);

            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    public function product($invoiceid)
    {
        try {
            $invoice = $this->invoiceItem->where('invoice_id', $invoiceid)->first();
            // dd($invoice);
            $name = $invoice->product_name;
            $product = $this->product->where('name', $name)->first();

            return $product;
        } catch (\Exception $ex) {
            app('log')->error($ex->getMessage());
            Bugsnag::notifyException($ex);

            throw new \Exception($ex->getMessage());
        }
    }

    public function GenerateOrder()
    {
        try {
            $products = [];
            $items = \Cart::getContent();
            foreach ($items as $item) {

               //this is product
                $id = $item->id;
                $this->AddProductToOrder($id);
            }
        } catch (\Exception $ex) {
            app('log')->error($ex->getMessage());
            Bugsnag::notifyException($ex);

            throw new \Exception('Can not Generate Order');
        }
    }

    public function AddProductToOrder($id)
    {
        try {
            $cart = \Cart::get($id);
            $client = \Auth::user()->id;
            $payment_method = \Input::get('payment_gateway');
            $promotion_code = '';
            $order_status = 'pending';
            $serial_key = '';
            $product = $id;
            $addon = '';
            $domain = '';
            $price_override = $cart->getPriceSumWithConditions();
            $qty = $cart->quantity;

            $planid = $this->price->where('product_id', $id)->first()->subscription;

            $or = $this->order->create(['client' => $client,
                'payment_method'                 => $payment_method, 'promotion_code' => $promotion_code,
                'order_status'                   => $order_status, 'serial_key' => $serial_key,
                'product'                        => $product, 'addon' => $addon, 'domain' => $domain,
                'price_override'                 => $price_override, 'qty' => $qty, ]);

            $this->AddSubscription($or->id, $planid);
        } catch (\Exception $ex) {
            app('log')->error($ex->getMessage());
            Bugsnag::notifyException($ex);

            throw new \Exception('Can not Generate Order for Product');
        }
    }

    public function AddSubscription($orderid, $planid)
    {
        try {
            $days = $this->plan->where('id', $planid)->first()->days;
            //dd($days);
            if ($days > 0) {
                $dt = \Carbon\Carbon::now();
                //dd($dt);
                $user_id = \Auth::user()->id;
                $ends_at = $dt->addDays($days);
            } else {
                $ends_at = '';
            }
            $this->subscription->create(['user_id' => \Auth::user()->id,
             'plan_id'                             => $planid, 'order_id' => $orderid, 'ends_at' => $ends_at, ]);
        } catch (\Exception $ex) {
            app('log')->error($ex->getMessage());
            Bugsnag::notifyException($ex);

            throw new \Exception('Can not Generate Subscription');
        }
    }

    public function GenerateInvoice()
    {
        try {
            $user_id = \Auth::user()->id;
            $number = rand(11111111, 99999999);
            $date = \Carbon\Carbon::now();
            $grand_total = \Cart::getSubTotal();

            $invoice = $this->invoice->create(['user_id' => $user_id,
                'number'                                 => $number, 'date' => $date, 'grand_total' => $grand_total, ]);
            foreach (\Cart::getContent() as $cart) {
                $this->CreateInvoiceItems($invoice->id, $cart);
            }
        } catch (\Exception $ex) {
            app('log')->error($ex->getMessage());
            Bugsnag::notifyException($ex);

            throw new \Exception('Can not Generate Invoice');
        }
    }

    public function CreateInvoiceItems($invoiceid, $cart)
    {
        try {
            $product_name = $cart->name;
            $regular_price = $cart->price;
            $quantity = $cart->quantity;
            $subtotal = $cart->getPriceSumWithConditions();

            $tax_name = '';
            $tax_percentage = '';

            foreach ($cart->attributes['tax'] as $tax) {
                $tax_name .= $tax['name'].',';
                $tax_percentage .= $tax['rate'].',';
            }

            //dd($tax_name);

            $invoiceItem = $this->invoiceItem->create(['invoice_id' => $invoiceid,
                'product_name'                                      => $product_name, 'regular_price' => $regular_price,
                'quantity'                                          => $quantity, 'tax_name' => $tax_name,
                 'tax_percentage'                                   => $tax_percentage, 'subtotal' => $subtotal, ]);
        } catch (\Exception $ex) {
            app('log')->error($ex->getMessage());
            Bugsnag::notifyException($ex);

            throw new \Exception('Can not create Invoice Items');
        }
    }
}
