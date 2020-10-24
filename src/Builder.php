<?php
namespace devebk\payme;
use devebk\payme\PaymentException;
use devebk\payme\Request;
use devebk\payme\Merchant;
use devebk\payme\Response;
use devebk\payme\DataFormat;
use devebk\payme\Models\Transaction;
use Illuminate\Support\Facades\Config;

class Builder {
    public $config;
    public $request;
    public $response;
    public $merchant;
    public $payment_system;
    public $minamount = 500;

    /**
     * Payme constructor.
     *
     * @param $param_array array   The URL to which the request is to be sent
     * @return Builder
     */
    public function to()
    {

        $this->config   = config('PaymeConfig');

        $this->response = new Response();
        $this->request  = new Request($this->response);
        $this->response->setRequest($this->request);
        $this->merchant = new Merchant($this->config, $this->response);

        $this->merchant->Authorize();

        return $this;
    }


    public function CheckPerformTransaction($model,$responsecheck=true)
    {

        $this->validateParams($this->request->params);


        if (!$model){
            $this->response->error(
                Response::ERROR_INVALID_ACCOUNT,
                'Object not fount.'
            );
        }

        if ($this->minamount > $this->request->params['amount']){
            $this->response->error(
                Response::ERROR_INVALID_AMOUNT,
                'Invalid amount for this object.'
            );
        }
        $active_transactions = $this->getModelTransactions($model, true);



        if ((count($active_transactions) > 0)){
            $this->response->error(
                Response::ERROR_INVALID_TRANSACTION,
                'There is other active/completed transaction for this object.'
            );
        }

    //    PaymentService::payListener($model,null,'before-pay');

if($responsecheck){        $this->response->success(['allow' => true]);}else{

    return true;
}

    }

    public function CheckTransaction()
    {
        $transaction  =  $this->findTransactionByParams($this->request->params);
        if (!$transaction) {
            $this->response->error(
                Response::ERROR_TRANSACTION_NOT_FOUND,
                'Transaction not found.'
            );
        }

        $detail = json_decode($transaction->detail,true);
        $this->response->success([
            'create_time'  => 1*$detail['create_time'],
            'perform_time' => 1*$detail['perform_time'],
            'cancel_time'  => 1*$detail['cancel_time'],
            'transaction'  => (string)$transaction->id,
            'state'        => 1*$transaction->state,
            'reason'       => ($transaction->comment && is_numeric($transaction->comment)) ? 1 * $transaction->comment : null,
        ]);
    }

    private function validateParams(array $params)
    {
        // for example, check amount is numeric

        if (!is_numeric($params['amount'])) {
            $this->response->error( Response::ERROR_INVALID_AMOUNT, 'Incorrect amount.');
        }

        // assume, we should have order_id
        if (!isset($params['key'])) {
            $this->response->error(
                Response::ERROR_INVALID_ACCOUNT,
                Response::message( 'Неверный код Счет.', 'Billing kodida xatolik.', 'Incorrect object code.'),
                'key'
            );
        }

        return true;
    }
    public function CreateTransaction($model)
    {

        $this->validateParams($this->request->params);


        $transaction = $this->findTransactionByParams($this->request->params);
        if ($transaction) {
            if ($transaction->state != Transaction::STATE_CREATED) {
                $this->response->error(
                    Response::ERROR_COULD_NOT_PERFORM,
                    'Transaction found, but is not active.'
                );
            }
        } else {


         $this->CheckPerformTransaction($model,false);



            if (DataFormat::timestamp2milliseconds(1 * $this->request->params['time']) - DataFormat::timestamp(true) >= Transaction::TIMEOUT) {
                $this->response->error(
                    Response::ERROR_INVALID_ACCOUNT,
                    Response::message(
                        'С даты создания транзакции прошло ' . Transaction::TIMEOUT . 'мс',
                        'Tranzaksiya yaratilgan vaqtdan ' . Transaction::TIMEOUT . 'ms o`tgan',
                        'Since create time of the transaction passed ' . Transaction::TIMEOUT . 'ms'
                    ),
                    'time'
                );
            }

            $create_time = DataFormat::timestamp(true);

            $detail = json_encode(array(
                'create_time'           => $create_time,
                'perform_time'          => null,
                'cancel_time'           => null,
                'system_time_datetime'  => DataFormat::timestamp2datetime($this->request->params['time'])
            ));

            $transaction = Transaction::create([
                'payment_system'        => 'payme',
                'system_transaction_id' => $this->request->params['id'],
                'amount'                =>1*($this->request->amount)/100,
                'currency_code'         => Transaction::CURRENCY_CODE_UZS,
                'state'                 => Transaction::STATE_CREATED,
                'updated_time'          => 1*$create_time,
                'comment'               => (isset($this->request->params['error_note'])?$this->request->params['error_note']:''),
                'detail'                => $detail,

                'transactionable_id'    => $model->id
            ]);
        }



        $this->response->success([
            'create_time' => 1*$transaction->updated_time,
            'transaction' => (string)$transaction->id,
            'state'       => 1*$transaction->state,
            'receivers'   => $transaction->receivers,
        ]);
    }

    public function PerformTransaction()
    {
        $transaction = $this->findTransactionByParams($this->request->params);


        if (!$transaction) {
            $this->response->error(Response::ERROR_TRANSACTION_NOT_FOUND, 'Transaction not found.');
        }

        switch ($transaction->state) {
            case Transaction::STATE_CREATED:
                if ($transaction->isExpired()) {
                    $transaction->cancel(Transaction::REASON_CANCELLED_BY_TIMEOUT);
                    $this->response->error(
                        Response::ERROR_COULD_NOT_PERFORM,
                        'Transaction is expired.'
                    );
                } else {

                    $perform_time               = DataFormat::timestamp(true);
                    $transaction->state         = Transaction::STATE_COMPLETED;
                    $transaction->updated_time  = $perform_time;

                    $detail = json_decode($transaction->detail,true);
                    $detail['perform_time']   =   $perform_time;
                    $detail = json_encode($detail);

                    $transaction->detail = $detail;

                    $transaction->update();



                    $this->response->success([
                        'transaction'  => (string)$transaction->id,
                        'perform_time' => 1*$perform_time,
                        'state'        => 1*$transaction->state,
                    ]);
                }
                break;

            case Transaction::STATE_COMPLETED:
                $detail = json_decode($transaction->detail,true);

                $this->response->success([
                    'transaction'  => (string)$transaction->id,
                    'perform_time' => 1*$detail['perform_time'],
                    'state'        => 1*$transaction->state,
                ]);

                break;

            default:

                $this->response->error(
                    Response::ERROR_COULD_NOT_PERFORM,
                    'Could not perform this operation.'
                );
                break;
        }
    }
    public function CancelTransaction()
    {
        $transaction = $this->findTransactionByParams($this->request->params);


        if (!$transaction) {
            $this->response->error(Response::ERROR_TRANSACTION_NOT_FOUND, 'Transaction not found.');
        }

        switch ($transaction->state) {

            case Transaction::STATE_CANCELLED:
            case Transaction::STATE_CANCELLED_AFTER_COMPLETE:
                $detail = json_decode($transaction->detail,true);
                $this->response->success([
                    'transaction' => (string)$transaction->id,
                    'cancel_time' => 1*$detail['cancel_time'],
                    'state'       => 1*$transaction->state,
                ]);
                break;


            case Transaction::STATE_CREATED:

                $transaction->cancel(1 * $this->request->params['reason']);

                $cancel_time               = DataFormat::timestamp(true);

                $detail = json_decode($transaction->detail,true);
                $detail['cancel_time']   =   $cancel_time;

                $transaction->update([
                    'updated_time'=> $cancel_time,
                    'detail' => json_encode($detail)]);




                $this->response->success([
                    'transaction' => (string)$transaction->id,
                    'cancel_time' => 1*$cancel_time,
                    'state'       => 1*$transaction->state,
                ]);
                break;

            case Transaction::STATE_COMPLETED:
                if (true) {
                    $transaction->cancel(1 * $this->request->params['reason']);

                    $detail = json_decode($transaction->detail,true);



                    $this->response->success([
                        'transaction' => (string)$transaction->id,
                        'cancel_time' => 1*$detail['cancel_time'],
                        'state'       => 1*$transaction->state,
                    ]);
                } else {
                    $this->response->error(
                        Response::ERROR_COULD_NOT_CANCEL,
                        'Could not cancel transaction. Order is delivered/Service is completed.'
                    );
                }
                break;
        }
    }

    public function findTransactionByParams($params)
    {
        $transaction = Transaction::all()->where('payment_system','=', 'payme')->where('system_transaction_id', $params['id'])->first();
        return $transaction;
    }
    public function getModelTransactions($model, $active = false)
    {

        $transactions = Transaction::all()->where('payment_system', 'payme')
           ->where('transactionable_id',$model->id);
        if ($active)
            $transactions = $transactions->where('state', Transaction::STATE_CREATED);
        return $transactions;
    }

    /**
     * @throws \devebk\payme\PaymentException
     */
    public function ChangePassword()
    {

        if (!isset($this->request->params['password']) || !trim($this->request->params['password'])) {
            $this->response->error(Response::ERROR_INVALID_ACCOUNT, 'New password not specified.', 'password');
        }


        if (config('PaymeConfig.PASSWORD') == $this->request->params['password']) {
            $this->response->error(Response::ERROR_INSUFFICIENT_PRIVILEGE, 'Insufficient privilege. Incorrect new password.');
        }




        config(['PaymeConfig.PASSWORD' => $this->request->params['password']]);




        if (config('PaymeConfig.PASSWORD') != $this->request->params['password']){
            $this->response->error(Response::ERROR_INTERNAL_SYSTEM, 'Internal System Error.');
        }

        $this->response->success(['success' => true]);
    }

    public function GetStatement()
    {
        // validate 'from'
        if (!isset($this->request->params['from'])) {
            $this->response->error(Response::ERROR_INVALID_ACCOUNT, 'Incorrect period.', 'from');
        }

        // validate 'to'
        if (!isset($this->request->params['to'])) {
            $this->response->error(Response::ERROR_INVALID_ACCOUNT, 'Incorrect period.', 'to');
        }

        // validate period
        if (1 * $this->request->params['from'] >= 1 * $this->request->params['to']) {
            $this->response->error(Response::ERROR_INVALID_ACCOUNT, 'Incorrect period. (from >= to)', 'from');
        }

        // get list of transactions for specified period
        $transactions  = $this->getReport($this->request->params['from'], $this->request->params['to']);

        // send results back
        $this->response->success(['transactions' => $transactions]);
    }
    public function getReport($from_date, $to_date)
    {
        $from_date = DataFormat::timestamp2datetime($from_date);
        $to_date   = DataFormat::timestamp2datetime($to_date);

        $transactions = Transaction::where('payment_system','payme')
            ->where('state',Transaction::STATE_COMPLETED)
            ->where('created_at','>=',$from_date)
            ->where('created_at','<=',$to_date)->get();
        // assume, here we have $rows variable that is populated with transactions from data store
        // normalize data for response
        $result = [];
        foreach ($transactions as $row) {
            $detail = json_decode($row['detail'],true);

            $result[] = [
                'id'           => (string)$row['system_transaction_id'], // paycom transaction id
                'time'         => 1 * $detail['system_time_datetime'], // paycom transaction timestamp as is
                'amount'       => 1 * $row['amount'],
                'account'      => [
                    'key' => 1 * $row['key'], // account parameters to identify client/order/service
                    // ... additional parameters may be listed here, which are belongs to the account
                ],
                'create_time'  => DataFormat::datetime2timestamp($detail['create_time']),
                'perform_time' => DataFormat::datetime2timestamp($detail['perform_time']),
                'cancel_time'  => DataFormat::datetime2timestamp($detail['cancel_time']),
                'transaction'  => (string)$row['id'],
                'state'        => 1 * $row['state'],
                'reason'       => isset($row['comment']) ? 1 * $row['comment'] : null,
                'receivers'    => isset($row['receivers']) ? json_decode($row['receivers'], true) : null,
            ];
        }
        return $result;

    }

    public function errormethod(){
        $this->response->error(
            Response::ERROR_METHOD_NOT_FOUND,
            'Method not found.',
            $this->request->method
        );
    }
}
