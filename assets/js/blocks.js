const settings = window.wc.wcSettings.getSetting( 'tubapay2_data', {} );
const label = window.wp.htmlEntities.decodeEntities( settings.title ) || window.wp.i18n.__( 'TubaPay Gateway', 'tubapay2' );
const Content = () => {
    return window.wp.htmlEntities.decodeEntities( settings.description || '' );
};
const Block_Gateway = {
    name: 'tubapay2',
    label: label,
    content: Object( window.wp.element.createElement )( Content, null ),
    edit: Object( window.wp.element.createElement )( Content, null ),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Block_Gateway );


function tubapay2_get_checkout_installments() {
    var response = '';
    jQuery.ajax({ type: "GET",
        url: "/tubapay_endpoint?call=get_installments",
        async: false,
        success : function(text)
        {
            response = text;
        }
    });

    return response;
}

function tubapay2_addTubaPayCalcInPayment(targetNode) {
    if (jQuery(targetNode).find('wc-block-components-radio-control-accordion-content') && jQuery('.tubapay-checkout-select').length === 0 ) {
        var tubapay_installments_selector = tubapay2_get_checkout_installments();

        if (tubapay_installments_selector === 'error') {
            console.log("error TubaPay");
        } else {
            jQuery(".wc-block-components-radio-control-accordion-option")
                .has("#radio-control-wc-payment-method-options-tubapay2")
                .find( ".wc-block-components-radio-control-accordion-content" )
                .append( tubapay_installments_selector );
        }
    }
}

function tubapay2_addObserverIfDesiredNodeAvailable() {
    var tubapayBox = document.querySelectorAll("#payment-method")[0];

    if(!tubapayBox) {
        //Wait 500ms and try again
        window.setTimeout(tubapay2_addObserverIfDesiredNodeAvailable,500);
        return;
    }

    const targetNode = document.getElementById("payment-method");

    tubapay2_addTubaPayCalcInPayment(targetNode);

    const config = { attributes: true, childList: true, subtree: true };
    const callback = (mutationList, observer) => {
        for (const mutation of mutationList) {
            if (mutation.type === "childList") {
                tubapay2_addTubaPayCalcInPayment(targetNode);
            }
        }
    };
    const observer = new MutationObserver(callback);
    observer.observe(targetNode, config);
}
tubapay2_addObserverIfDesiredNodeAvailable();