function tubapay2_check_connection() {
    var response = '';
    jQuery.ajax({ type: "GET",
        url: "/tubapay_endpoint?call=check_connection",
        async: false,
        success : function(text)
        {
            response = text;
        }
    });

    alert(response);
}

jQuery(document).ready(function() {
    var tubapay2_check_connection_btn = '<button id=\"tubapay2_check_connection_btn\" type=\"button\" onclick=\'tubapay2_check_connection()\'>Sprawdź</button>'
    jQuery( "#woocommerce_tubapay2_connection_status" ).hide();
    jQuery( "#woocommerce_tubapay2_connection_status" ).after( tubapay2_check_connection_btn );
});