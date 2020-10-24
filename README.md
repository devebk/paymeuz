"# paymeuz" 

<h3>install</h3>

<label>You can install the package via composer:</label>
<br/>
<code> composer require devebk/paymeuz </code>
<br/>
<label>
Publishing required files of package: 
</labe>
<br/>
<code>php artisan vendor:publish --provider="devebk\paymeuz\PaymeServiceProvider"</code>
<br/>
<label>
Migrate tables:
</labe>
<br/>
<code>php artisan migrate</code>

<h3>Usage</h3>

<h6>You can use the package via controller</h6>
<pre>
<code>
use devebk\paymeuz\Facades\Payme;


class TestController extends Controller
{
   public function index(){


       $payme =  Payme::to();

       switch ($payme->request->method) {
           case 'CheckPerformTransaction':
               $model = User::find($payme->request->params['key']);
               $payme->CheckPerformTransaction($model);
               break;
           case 'CheckTransaction':
               $payme->CheckTransaction();
               break;
           case 'CreateTransaction':
               $model = User::find($payme->request->params['key']);
               $payme->CreateTransaction($model);
               break;
           case 'PerformTransaction':
               $payme->PerformTransaction();
               break;
           case 'CancelTransaction':
               $payme->CancelTransaction();
               break;
           case 'ChangePassword':
               $payme->ChangePassword();
               break;
           case 'GetStatement':
               $payme->GetStatement();
               break;
           default:
               $payme->errormethod();
       }
       

   }
}

</code>
</pre>

<h3>Usage config file</h3>
<h6>app\PaymeConfig.php</h6>
<pre>
<code>
        'LOGIN'   => 'Paycom' /* Payme.uz login*/,
        'PASSWORD'   => '' /* Payme.uz Test key or key*/,
        'SECRET_KEY'   => '' /* Payme.uz secret_key*/,
        'MERCHANT_ID'   => '' /* Payme.uz merchant id*/,
        'SERVICE_ID'   => '' /* Payme.uz service id*/,
        'MERCHANT_USER_ID'   => ''/* Payme.uz merchant user id*/,
        'MIN_AMOUNT'   => 500,  /*Minimum amount of payment*/
</code>
</pre>
<br/>
<h3>integration check link</h3>
<code><a href="https://test.paycom.uz/">https://test.paycom.uz/</a></code>

