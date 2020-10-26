{extends file="parent:backend/_base/layout.tpl"}

{block name="content/main"}
   <form class="form-horizontal pairing-form" method="post">
      <div class="form-group">
         <label for="windowTitle" class="col-sm-2 control-label">
            Paring (get is from
               <a target="_blank" href="http://btcpay.lamp-solutions.de:4080/api-tokens">
                  {$api_url}
               </a>)
         </label>
         <div class="col-sm-10">
            <input type="hidden" name="pair_now" value="pair_now">
            <input type="text" class="form-control" id="windowTitle" name="ParingCode" required placeholder="{s name="pair_placeholder"
            namespace="backend/b_t_c_pay_payment_check"}Your paring code...{/s}">
         </div>
      </div>



      <div class="form-group">
         <div class="col-sm-offset-2 col-sm-10">
            <button type="submit" class="btn btn-primary">
               {s name="pair_now" namespace="backend/b_t_c_pay_payment_check"}Pair now{/s}
            </button>
         </div>
      </div>
   </form>
   {if $token}
      <h2>
         {s name="congrats" namespace="backend/b_t_c_pay_payment_check"}Congratulations your Token is{/s}
          {$token} </h2>
   {/if}
   {if $error}
      <h2>{s name="paring_error" namespace="backend/b_t_c_pay_payment_check"}An error has happend{/s}
         </h2>
      <pre>
         {$error}
      </pre>
      {if $request}
         <pre>
            {$request}
         </pre>
      {/if}
      {if $response}
         <pre>
            {$response}
         </pre>
      {/if}
   {/if}
{/block}