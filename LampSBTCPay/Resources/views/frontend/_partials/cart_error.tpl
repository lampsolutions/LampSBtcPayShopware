{if $PaymentError}
    <div class="basket--info-messages">
        <div class="alert is--error is--rounded">
            {* Icon column *}
            <div class="alert--icon">
                <i class="icon--element icon--cross"></i>
            </div>

            {* Content column *}
            <div class="alert--content is--strong">
                {s name="lampsbtcpay" namespace="frontend/lampsbtcpay"}Bei Ihrem Payment-Vorgang ist ein Fehler aufgetreten.{/s}
            </div>
        </div>
    </div>
{/if}
