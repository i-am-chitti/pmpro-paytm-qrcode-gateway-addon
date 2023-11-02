const orderElm = document.querySelector("#order-id");
let orderId = "";
if (orderElm) {
  orderId = orderElm.value;
}

const merchantUpiElm = document.querySelector("#merchant-upi");
let merchantUpi = "";
if (merchantUpiElm) {
  merchantUpi = merchantUpiElm.value;
}

const amountElm = document.querySelector("#amount");
let amount = "";
if (amountElm) {
  amount = amountElm.value;
}

if (orderId && merchantUpi && amount) {
  const paymentLink = `upi://pay?cu=INR&pa=${merchantUpi}&pn=Merchant&am=${amount}&mam=${amount}&tr=${orderId}`;
  new QRCode(document.getElementById("paytm-qrcode"), paymentLink);
}
