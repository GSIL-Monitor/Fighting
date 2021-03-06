<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

use DB;
use Illuminate\Support\Facades\Redis;
use App\Jobs\CloseOrder;

class OrderModel extends Model{

    public $_tabName = 'g_orders';

/**
 * 创建一个订单
 * Author Amber
 * Date 2018-08-01
 * Params [params]
 * @param  string $value [description]
 * @return [type]        [description]
 */
    public function store($order)
    {
      
    	$bool = DB::table('g_orders')
            ->insertGetId($order);

        return $bool;
    }


    public function store_items($items)
    {
    	foreach ($items as $key => $value) {
    	   $order = DB::table('g_order_items')
            ->insert($value);
    	}
     	return $order;
    }

    public function wait_paylist($user_id='')
    {
    	$bool = DB::table('g_orders')
            ->select('id','no','total_amount','remark','paid_status','creatorder_at','expiration_at')
            ->where('user_id',$user_id)
            ->where('paid_status',"待支付")
            ->get();
        $objects = json_decode(json_encode($bool), true);//未支付的订单列表
        
        $CloseOrder = array();
        foreach ($objects as $key => $value) {//关闭支付超时的订单
            if(time() > $value['expiration_at']){//判断订单过期时间是否小于当前时间
                $del_order = DB::table('g_orders')
                    ->where('id', $value['id'])
                    ->update(['paid_status' => '已关闭']);
                if($del_order){//修改skuid商品的状态 订单状态改为已关闭，并还原对应的库存
                    $small_order = DB::table('g_order_items')
                     ->select()
                     ->where('order_id',$value['id'])
                     ->get();
                     $small = json_decode(json_encode($small_order), true);
                     foreach ($small as $k => $v) {
                            $del_item =  DB::update('update  g_goods set inventory = inventory + '.$v['amout'].' where id = '.$v['goods_id'].'');
                     }
                }
            }
            else{//未支付订单的详细商品列表
                $pay_items = collect([]);
                foreach ($objects as $key => $value) {
                   $arr = DB::table('g_order_items')
                    ->where('order_id',$value['id'])
                    ->get(); 
                     $pay_items->push($arr);
                }
			          $pay_items = $pay_items->flatten();
                $pay_items = json_decode(json_encode($pay_items), true);
                $list = array();
                foreach ((array)$pay_items as $k => $v) {
                         $goods = DB::table('g_productSkus')
                            ->select('g_product.id','g_product.goods_thumb','g_product.goods_name','g_productSkus.title')
                            ->join('g_product','g_productSkus.product_id','=','g_product.id')
                            ->where('g_productSkus.id',$v['goods_id'])
                            ->first();
                            $list[$k]['goods'] = (array)$goods;
                            $list[$k]['amout'] = $v['amout'];
                            $list[$k]['price'] = $v['price'];
                            $list[$k]['total_amount'] = $v['price']*$v['amout'];
               
                 }
            }

        }
       return $list;
    
}

        
/**
 * 待支付详情页
 * Author Amber
 * Date 2018-08-09
 * Params [params]
 * @param  string $value [description]
 * @return [type]        [description]
 */
    public function wait_pay($user_id = '',$order_id = '',$goods_id = '')
    {
        $order = DB::table($this->_tabName)
            ->select('id','no','address','total_amount','remark','expiration_at','creatorder_at')
            ->where('id', $order_id)
            ->where('user_id',$user_id)
            ->first();
        if(empty($order)){
            return False;
        }
        $orders = get_object_vars($order);
        //上边查的是收货地址
        //下边我们要查的是相关的订单
        $order_item = DB::table('g_order_items')
                ->select('g_order_items.price','g_product.goods_thumb','g_order_items.goods_id','g_product.goods_name','g_order_items.amout')
                ->join('g_product','g_order_items.goods_id','=','g_product.id')
                ->where('order_id',$order_id)
                ->where('goods_id',$goods_id)
                ->first();          
        if(empty($order_item)){
            return False;
        }
        $order_items = get_object_vars($order_item);
        $arr = array_merge($orders,$order_items);
        return $arr ? $arr : False; 
    }
    /**
     * 订单列表页 
     * Author Amber
     * Date 2018-12-24
     * Params [params]
     * @param string $value [description]
     */
    public function goods_orderlist($user_id='',$paid_status='')
    {
       $bool = DB::table('g_orders')
            ->select('g_order_items.id','g_order_items.order_id','no','g_orders.total_amount','g_orders.address','g_orders.creatorder_at','g_order_items.goods_id','g_order_items.amout','g_order_items.price')
            ->join('g_order_items','g_orders.id','=','g_order_items.order_id')
            ->where('user_id',$user_id)
            ->where('paid_status',$paid_status)
            ->get();
        $objects = json_decode(json_encode($bool), true);
        // print_r($objects);die;
        $goods_item = array();
        foreach ($objects as $key => $value) {
          $bool = DB::table('g_productSkus')
            ->select('g_productSkus.id','g_productSkus.sku_thumb','g_product.goods_name','g_productSkus.title','g_productSkus.pricenow')
            ->join('g_product','g_productSkus.product_id','=','g_product.id')
            ->where('g_productSkus.id',$value['goods_id'])
            ->first();
          $goods_item[$key] = json_decode(json_encode($bool), true);//未发货列表
          $goods_item[$key]['order_id'] = $value['order_id'];
          $goods_item[$key]['order_itemid'] = $value['id'];
          $goods_item[$key]['total_amount'] = $value['total_amount'];
          $goods_item[$key]['amout'] = $value['amout'];
          $goods_item[$key]['address'] = $value['address'];
          $goods_item[$key]['creatorder_at'] = $value['creatorder_at'];

        }
        return $goods_item;
    }
/**
 * 待发货列表 
 * Author Amber
 * Date 2018-12-07
 * Params [params]
 * @param  string $user_id [description]
 * @return [type]          [description]
 */
    public function wait_sendlist($user_id='')
    {
      
        $paid_status = "待发货";
        $res = $this->goods_orderlist($user_id,$paid_status);
        return $res;
      }
/**
 * 待收货列表 
 * Author Amber
 * Date 2018-12-07
 * Params [params]
 * @param  string $user_id [description]
 * @return [type]          [description]
 */
    public function ReceiptList($user_id='')
    {
      
        $paid_status = "待收货";
        $res = $this->goods_orderlist($user_id,$paid_status);
        return $res;
    }
/**
 * 已完成列表 
 * Author Amber
 * Date 2018-12-07
 * Params [params]
 * @param  string $user_id [description]
 * @return [type]          [description]
 */
    public function Overlist($user_id='')
    {
      
        $paid_status = "已完成";
        $res = $this->goods_orderlist($user_id,$paid_status);
        return $res;
    }
/**
 * 订单详情页 
 * Author Amber
 * Date 2018-12-24
 * Params [params]
 * @param  [type] $order_id    [description]
 * @param  [type] $paid_status [description]
 * @return [type]              [description]
 */
    public function goods_orderitem($order_id,$paid_status)
    {
        $bool = DB::table('g_orders')
          ->select('g_order_items.id','g_order_items.order_id','no','g_orders.total_amount','g_orders.address','g_orders.creatorder_at','g_order_items.goods_id','g_order_items.amout','g_order_items.price')
          ->join('g_order_items','g_orders.id','=','g_order_items.order_id')
          ->where('g_orders.id',$order_id)
          ->where('g_orders.paid_status',$paid_status)
          ->get();
        $objects = json_decode(json_encode($bool), true);
        if(empty($objects)){
          return False;
        }
        $goods_item = array();
        foreach ($objects as $key => $value) {
          $bool = DB::table('g_productSkus')
            ->select('g_productSkus.id','g_productSkus.sku_thumb','g_product.goods_name','g_productSkus.title','g_productSkus.pricenow')
            ->join('g_product','g_productSkus.product_id','=','g_product.id')
            ->where('g_productSkus.id',$value['goods_id'])
            ->first();
          $goods_item[$key] = json_decode(json_encode($bool), true);
          $goods_item[$key]['order_itemid'] = $value['id'];
          $goods_item[$key]['amout'] = $value['amout'];

        }
          $goods_item['order_id'] = $value['order_id'];
          $goods_item['no'] = $value['no'];
          $goods_item['address'] = $value['address'];
          $goods_item['total_amount'] = $value['total_amount'];
          $goods_item['creatorder_at'] = $value['creatorder_at'];
        return $goods_item;
    }
/*
  待发货详情页
 */
    public function wait_senditem($order_id='')
    {
        $paid_status = "待发货";
        $res = $this->goods_orderitem($order_id,$paid_status);
        return $res;
    }
/*
  待收货详情页
 */
    public function Receiptitem($order_id='')
    {
        $paid_status = "待收货";
        $res = $this->goods_orderitem($order_id,$paid_status);
        return $res;
    }
/*
  已完成详情页
 */
    public function Overitem($order_id='')
    {
        $paid_status = "已完成";
        $res = $this->goods_orderitem($order_id,$paid_status);
        return $res;
    }    
}

