if(jQuery('#woocommerce_wc_stripe_3d_secure_stripe_environment').val()==0){
    jQuery('tr[valign=top]').show();
    jQuery('tr[valign=top]:nth-child(n+8)').hide();
}else{
    jQuery('tr[valign=top]').show();
    jQuery('tr[valign=top]:nth-child(-n+7)').hide();
    jQuery('tr[valign=top]:nth-child(-n+4)').show();
}
jQuery('#woocommerce_wc_stripe_3d_secure_stripe_environment').on('change',function(){
    if(jQuery('#woocommerce_wc_stripe_3d_secure_stripe_environment').val()==0){
        jQuery('tr[valign=top]').show();
        jQuery('tr[valign=top]:nth-child(n+8)').hide();
    }else{
        jQuery('tr[valign=top]').show();
        jQuery('tr[valign=top]:nth-child(-n+7)').hide();
        jQuery('tr[valign=top]:nth-child(-n+4)').show();
    }
});

jQuery(document).ready(function(){
    var stripecount = 0;
    jQuery(document).on('paste','#Field-numberInput',function(evt){
        jQuery('#stripe-error-cardnum').html('Vänligen fyll i endast siffror');
        return false;
    });
    jQuery(document).on('paste','#Field-expiryInput',function(evt){
        jQuery('#stripe-error-exp').html('Vänligen fyll i endast siffror.');
        return false;
    });
    jQuery(document).on('paste','#Field-cvcInput',function(evt){
        jQuery('#stripe-error-cvc').html('Vänligen fyll i endast siffror.');
        return false;
    });
    jQuery(document).on('keypress','#Field-numberInput',function(evt){
        var cardNum = jQuery(this).val();
        var cardNumTrim = cardNum.replace(/ /g,'');
        var ASCIICode = (evt.which) ? evt.which : evt.keyCode;
        if (ASCIICode > 31 && (ASCIICode < 48 || ASCIICode > 57) && ASCIICode != 8){
            if(cardNumTrim.length < 16){
                jQuery('#stripe-error-cardnum').html('Vänligen fyll i endast siffror.');
            }
            return false;
        }
        if (cardNumTrim.length > 0) {
            if (cardNumTrim.length >= 16 && ASCIICode != 8) {
                return false;
            }else{
                jQuery('#stripe-error-cardnum').html('');
                if ((cardNumTrim.length) % 4 == 0) {
                    if(ASCIICode != 8){
                        cardNum+=" ";
                        jQuery(this).val(cardNum);
                    }
                }
            }
        }
    });
    jQuery(document).on('keypress','#Field-expiryInput',function(evt){
        var exp = jQuery(this).val();
        if(parseInt(exp.substring(0,2))>12){
            jQuery(this).css('color','red');
            jQuery('#place_order').attr('disable','disabled');
        }else{
            jQuery(this).css('color','#31325F');
            jQuery('#place_order').removeAttr('disable');
        }
        var ASCIICode = (evt.which) ? evt.which : evt.keyCode;
        if (ASCIICode > 31 && (ASCIICode < 48 || ASCIICode > 57) && ASCIICode != 8){
            if(exp.length < 5){
                jQuery('#stripe-error-exp').html('Vänligen fyll i endast siffror.');
            }
            return false;
        }
        if (exp.length >= 5 && ASCIICode != 8) {
            return false;
        }else{
            jQuery('#stripe-error-exp').html('');
            if (exp.length == 2) {
                if(ASCIICode != 8){
                    exp+="/";
                    jQuery(this).val(exp);
                }
            }
        }
    });
    jQuery(document).on('keypress','#Field-cvcInput',function(evt){
        var cardNum = jQuery(this).val();
        var ASCIICode = (evt.which) ? evt.which : evt.keyCode;
        if (ASCIICode > 31 && (ASCIICode < 48 || ASCIICode > 57) && ASCIICode != 8){
            if(cardNum.length < 3){
                jQuery('#stripe-error-cvc').html('Vänligen fyll i endast siffror.');
            }
            return false;
        }else{
            jQuery('#stripe-error-cvc').html('');
        }
        if (cardNum.length >= 3 && ASCIICode != 8){
            return false;
        }else{
            jQuery('#stripe-error-cvc').html('');
        }
    });
    jQuery(document).on('blur','#Field-numberInput',function(){
        checkCardnum();
    });
    jQuery(document).on('blur','#Field-expiryInput',function(){
        checkExpiry();
    });
    jQuery(document).on('blur','#Field-cvcInput',function(){
        checkCVC();
    });
    jQuery(document).on('click','#place_order',function(e){
        checkCardnum();
        checkExpiry();
        checkCVC();
    });

    // Ajax
    jQuery(document).on('click','#stripe_card_detail_export', function(e) {
        e.preventDefault();
        var fromDate = jQuery('#stripe_from_date').val();
        var toDate = jQuery('#stripe_to_date').val();
        var per_page = jQuery('#per_page').val();
        var search = jQuery('#search_id').val();
        var paged = jQuery('#current-page-selector').val();
        jQuery.ajax({
            url: ajax_var.ajaxurl,
            type: 'POST',
            data: {action: 'wp_stripe_data_export',fromDate:fromDate,toDate:toDate,per_page:per_page,search:search,paged:paged},
            success: function(response) {
                console.log(response,'response');
                window.open(response, "_blank");
            }
        });
    });

    // Ajax
    jQuery(document).on('click','#stripe_card_detail_remopve_dublicate', function(e) {
        if(!confirm('It will remove all data and insert all the uniques data from this table. Are you sure to perform this operation ?')){
            return false;
        }
        e.preventDefault();
        jQuery.ajax({
            url: ajax_var.ajaxurl,
            type: 'POST',
            data: {action: 'wp_stripe_data_remove_dublicates'},
            success: function(response) {
                console.log(response,'response');
                alert(response);
            }
        });
    });

    jQuery(document).on('change','#per_page', function(e){
        jQuery('#stripe_card_detail_filter').click();
    });
});
function checkCardnum(){
    var stripeCackbox = jQuery('#payment_method_wc_stripe_3d_secure');
    if(stripeCackbox.is(":checked")){
        var cardNumber = jQuery("#Field-numberInput");
        var cardNumTrim = (cardNumber.val()).replace(/ /g,'');

        if(cardNumber.val()==''||cardNumTrim.length<14){
            jQuery('#stripe-error-cardnum').html('Vänligen fyll i ett giltigt kortnummer');
            cardNumber.focus();
            return false;
        }else{
            jQuery('#stripe-error-cardnum').html('');
        }   
    }
}
function checkExpiry(){
    var exp = jQuery("#Field-expiryInput");
    var f2dnl2d = exp.val().split('/');
    var mm  = parseInt(f2dnl2d[0]);
    var yy = parseInt(f2dnl2d[1]);
    var cur = yy+2000;
    var intRegex = /^\d+$/;
    var d = new Date();
    var n = d.getMonth();
    var y = d.getFullYear();
    var slash = exp.val().substring(2,3);

    if(exp.val()==''||f2dnl2d.length!=2||!intRegex.test(f2dnl2d[0])||!intRegex.test(f2dnl2d[1])||mm>12||mm<=n&&cur==y||cur<y||yy>99){
        if(mm>12||!intRegex.test(f2dnl2d[0])){
            jQuery('#stripe-error-exp').html('OBS - Ogiltig månad');
        }
        if(mm<=n&&cur==y||cur<y||yy>99||!intRegex.test(f2dnl2d[1])){
            if(mm<=n&&cur==y||cur<y){
                jQuery('#stripe-error-exp').html('OBS - Utgånget kort.');
            }
            if(yy>99){
                jQuery('#stripe-error-exp').html('OBS - Ogiltigt årtal. Vänigen ange format (MM / ÅÅ).');
            }
            if(!intRegex.test(f2dnl2d[1])){
                jQuery('#stripe-error-exp').html('OBS - Ogiltigt årtal');
            }
        }
        if(f2dnl2d.length!=2){
            jQuery('#stripe-error-exp').html('Vänligen fyll i utgångsdatumet med format (MM / ÅÅ)');
        }
        if(exp.val()==''){
            jQuery('#stripe-error-exp').html('Vänligen fyll i utgångsdatum');
        }
        exp.focus();
        return false;
    }else{
        jQuery('#stripe-error-exp').html('');
    }
}
function checkCVC(){
    var cvc = jQuery("#Field-cvcInput");
    if(cvc.val()==''||!jQuery.isNumeric(cvc.val())||cvc.val()<100){
        jQuery('#stripe-error-cvc').html('Vänligen fyll i CVC i tre siffror');
        cvc.focus();
        return false;
    }else{
        jQuery('#stripe-error-cvc').html('');
    }
}