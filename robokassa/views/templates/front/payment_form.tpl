<form action="{$action}" id="payment-form">

  <input type=hidden name=MerchantLogin value="{$mrh_login}">

  <input type=hidden name=OutSum value="{$out_summ}">

  <input type=hidden name=InvId value="{$inv_id}">

  <input type=hidden name=Description value="{$inv_desc}">

  <input type=hidden name=SignatureValue value="{$crc}">

  <input type=hidden name=IncCurrLabel value="{$in_curr}">

  <input type=hidden name=Culture value="{$culture}">
</form>