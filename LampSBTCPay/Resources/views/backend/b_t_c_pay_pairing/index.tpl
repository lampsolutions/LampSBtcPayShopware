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
            <input type="text" class="form-control" id="windowTitle" name="ParingCode" required placeholder="Your paring code...">
         </div>
      </div>



      <div class="form-group">
         <div class="col-sm-offset-2 col-sm-10">
            <button type="submit" class="btn btn-primary">Pair now</button>
         </div>
      </div>
   </form>
   {if $token}
      <h2>Congratulations your Token is {$token} </h2>
   {/if}
   {if $error}
      <h2>Oh no, error has happend</h2>
      <pre>
         {$error}
      </pre>
      <pre>
         {$request}
      </pre>
      <pre>
         {$response}
      </pre>
   {/if}
{/block}