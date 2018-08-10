    @extends('themes.default1.layouts.master')
    @section('content-header')
    <h1>
    Create New Payment
    </h1>
      <ol class="breadcrumb">
            <li><a href="{{url('/')}}"><i class="fa fa-dashboard"></i> Home</a></li>
            <li><a href="{{url('clients')}}">All Users</a></li>
            <li><a href="{{url('clients/'.$clientid)}}">View User</a></li>
            <li class="active">New Payment</li>
          </ol>
    @stop
    @section('content')
    <div class="box box-primary">
    	 <div class="box-header">
            @if (count($errors) > 0)
                    <div class="alert alert-danger">
                         <i class="fa fa-alert"></i>
                         <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <strong>Whoops!</strong> There were some problems with your input.<br>
                        <ul>
                            @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    @if(Session::has('success'))
                    <div class="alert alert-success alert-dismissable">
                         <i class="fa fa-check"></i>
                         <b>{{Lang::get('message.success')}}!</b>
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        {{Session::get('success')}}
                    </div>
                    @endif
                    <!-- fail message -->
                    @if(Session::has('fails'))
                    <div class="alert alert-danger alert-dismissable">
                        <i class="fa fa-ban"></i>
                        <b>{{Lang::get('message.alert')}}!</b> {{Lang::get('message.failed')}}.
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        {{Session::get('fails')}}
                    </div>
                    @endif
            {!! Form::open(['url'=>'newPayment/receive/'.$clientid,'method'=>'post']) !!}

            <h4>{{Lang::get('message.new-payment')}} <button type="submit" class="form-group btn btn-primary pull-right" id="submit"><i class="fa fa-floppy-o">&nbsp;&nbsp;</i>{!!Lang::get('message.save')!!}</button></h4>

        </div>

    	<div class="box-body">

            <div class="row">

                <div class="col-md-12">

                    

                    <div class="row">

                        <div class="col-md-4 form-group {{ $errors->has('invoice_status') ? 'has-error' : '' }}">
                            <!-- first name -->
                            {!! Form::label('payment_date',Lang::get('message.date-of-payment'),['class'=>'required']) !!}
                            {!! Form::text('payment_date',null,['class' => 'form-control']) !!}

                        </div>

                        <div class="col-md-4 form-group {{ $errors->has('payment_method') ? 'has-error' : '' }}">
                            <!-- last name -->
                            {!! Form::label('payment_method',Lang::get('message.payment-method'),['class'=>'required']) !!}
                            {!! Form::select('payment_method',[''=>'Choose','cash'=>'Cash','check'=>'Check','online payment'=>'Online Payment','razorpay'=>'Razorpay'],null,['class' => 'form-control']) !!}

                        </div>

                        
                        <div class="col-md-4 form-group {{ $errors->has('amount') ? 'has-error' : '' }}">
                            <!-- first name -->
                            {!! Form::label('amount',Lang::get('message.amount'),['class'=>'required']) !!}
                            {!! Form::text('amount',null,['class' => 'form-control','id'=>'amount']) !!}

                        </div>

                        


                    </div>


                </div>

            </div>

        </div>



    </div>
    <div class= "box box-primary">
        
                        <div class="box-body">
                        <div class="row">

                            <div class="col-md-12">
                                <table id="payment-table" class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="select_all" onchange="checking(this)" class="main"></th>
                                            <th>{{Lang::get('message.date')}}</th>
                                            <th>{{Lang::get('message.invoice_number')}}</th>
                                            <th>{{Lang::get('message.total')}}</th>
                                            <th>{{Lang::get('message.amount_pending')}}</th>
                                            <th>{{Lang::get('message.pay')}}</th>
                                            
                                           
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($invoices as $invoice)
                                        <?php
                                         if($invoice->currency == 'INR')
                                            $currency = '₹';
                                            else
                                            $currency = '$'; 
                                        $payment = \App\Model\Order\Payment::where('invoice_id',$invoice->id)->select('amount')->get();
                                         $c=count($payment);
                                           $sum= 0;
                                           for($i=0 ;  $i <= $c-1 ; $i++)
                                           {
                                             $sum = $sum + $payment[$i]->amount;
                                           }
                                           $pendingAmount = ($invoice->grand_total)-($sum);
                                        ?>
                                       
                                        <tr>
                                             <td class="selectedbox1">
                                                 <input type="checkbox"  class="selectedbox" name='selectedcheckbox' value="{{$invoice->id}}">
                                            </td>
                                            <td>
                                                  {{$invoice->date}}
                                            </td>
                                            <td class="invoice-number">
                                                <a href="{{url('invoices/show?invoiceid='.$invoice->id)}}">{{$invoice->number}}</a>
                                            </td>
                                            <td contenteditable="true" class="invoice-total"> 
                                               {{$invoice->grand_total}}
                                            </td>
                                            <td id="pendingamt">
                                                  <input type="text" class="pendingamt" name="pending" value ="{{$pendingAmount}}">
                                               
                                            </td>
                                            <td class="changeamt">
                                                <input type="text" class="changeamt" name="amount" id="{{$invoice->id}}">
                                               
                                            </td>
                                           

                                        </tr>
                                      
                                        @empty 
                                        <tr>
                                            <td>No Invoices</td>
                                        </tr>
                                        @endforelse



                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
    </div>
    @stop
    @section('datepicker')
    <script type="text/javascript">
    $(function () {
        $('#payment_date').datetimepicker({
            format: 'YYYY-MM-DD'
        });
    });
    </script>
    <script>
         function checking(e){

            $('#payment-table').find("td input[type='checkbox']").prop('checked', $(e).prop('checked'));
         }

         $(document).ready(function () {
        /* Get the checkboxes values based on the class attached to each check box */
        $(".selectedbox").click(function() {
            getValueUsingClass();
        });
         });

         function getValueUsingClass(){
        /* declare an checkbox array */
        var chkArray = [];
        
        /* look for all checkboes that have a class 'chk' attached to it and check if it was checked */
        $(".selectedbox:checked").each(function() {
            chkArray.push($(this).val());
        });      
        amountChange();
      var amt = $("#amount").val();
       function amountChange()
         {
        $('.selectedbox').change(function () {
          $('.changeamt').val('');
            if(chkArray[0] > 0){
             for (var i in chkArray) {
                 $('#'+chkArray[i]).val(amt);
                 var pending= $('#pendingamt').val();
                 console.log(pending); 
                }
            }
        });
          }

    $('#amount').change(function () {
          for (var i in chkArray) {
             $('#'+chkArray[i]).val($(this).val());
            }
        
    });  
  }
     
  </script>

    @stop