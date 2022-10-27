<?php 
/* Template Name: PageWithoutSidebar */
// session_start();
require 'vendor/autoload.php';
require_once(WP_STRIPE_3D_SECURE_PAYMENT_PLUGIN_PATH.'wp-ajax/stripe-3dSecure-ajax.php');
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>BETALNING</title>
</head>

<style>
  body{
        background: #e2dddd;
  }
  .loader-page{

    position: relative;
  }
.loading {
  height: 0;
  width: 0;
  padding: 15px;
  border: 6px solid #ccc;
  border-right-color: #888;
  border-radius: 22px;
  -webkit-animation: rotate 1s infinite linear;
  /* left, top and position just for the demo! */
  position: absolute;
     left: 0;
    right: 0;
    margin: auto;
        top: 134px;
}
.loading-para{
  font-size: 20px;
    font-weight: bold;
    text-align: center;
}
.loader-text{
  padding-top: 13rem;
    position: relative;
    text-align: center;
}
@-webkit-keyframes rotate {
  /* 100% keyframe for  clockwise. 
     use 0% instead for anticlockwise */
  100% {
    -webkit-transform: rotate(360deg);
  }
}

  </style>

<body>

  <div class="loader-page">
 <div class="loading"></div>

 <div class=loader-text>
  <p class="loading-para">Betalning behandlas</p>
  <p class="loading-para">Stäng inte detta fönster...</p>
</div>
</div>
  <?php
      $payment_intent_id = $_GET['payment_intent'];
      $order_id = $_GET['order_id'];
  ?>
</body>
</html>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

<script type="text/javascript">
  jQuery(document).ready(function(){
    var payment_intent_id = "<?=$payment_intent_id; ?>";
    var order_id = <?=$order_id?>;
    var per_page = jQuery('#per_page').val();
    var search = jQuery('#search_id').val();
    var paged = jQuery('#current-page-selector').val();
    jQuery.ajax({
        url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
        type: 'POST',
        data: {action:'wp_stripe_data_update_status',payment_intent_id:payment_intent_id,order_id:order_id},
        success: function(response) {
          setTimeout(()=>
            window.location.href = response,5000
            );
        }
    });
  });
</script>