{extends file="parent:frontend/checkout/cart.tpl"}

{block name="frontend_checkout_cart_error_messages"}

    {include file="frontend/_partials/cart_error.tpl"}

    {$smarty.block.parent}
{/block}
