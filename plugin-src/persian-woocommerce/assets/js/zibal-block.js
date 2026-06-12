(() => {
    const {getSetting} = window.wc.wcSettings;
    const {registerPaymentMethod} = window.wc.wcBlocksRegistry;
    const {createElement} = window.wp.element;
    const {decodeEntities} = window.wp.htmlEntities;

    const pw_wc_zibal_settings = getSetting('wc_zibal_data', {});

    // Prepare label and icon
    const pw_wc_zibal_label = decodeEntities(pw_wc_zibal_settings.title) || 'زیبال';
    const pw_wc_zibal_label_element = createElement('span', null, pw_wc_zibal_label);

    const pw_wc_zibal_icon_element = createElement('img', {
        src: pw_wc_zibal_settings.icon,
        alt: decodeEntities(pw_wc_zibal_settings.title),
        style: {marginInline: '10px'},
    });

    // Create gateway title element
    const pw_wc_zibal_title_element = createElement(
        'span',
        {
            style: {
                display: 'flex',
                justifyContent: 'space-between',
                width: '100%',
            },
        },
        [pw_wc_zibal_label_element, pw_wc_zibal_icon_element]
    );

    // Create gateway description element
    const pw_wc_zibal_description = () =>
        decodeEntities(
            pw_wc_zibal_settings.description ||
            'پرداخت امن به وسیله کلیه کارت های عضو شتاب از طریق درگاه زیبال '
        );

    const pw_wc_zibal_description_element = createElement(pw_wc_zibal_description);

    // Register payment method
    const pw_wc_zibal_gateway_block = {
        name: 'wc_zibal',
        label: pw_wc_zibal_title_element,
        content: pw_wc_zibal_description_element,
        edit: pw_wc_zibal_description_element,
        canMakePayment: () => true,
        ariaLabel: pw_wc_zibal_label,
        supports: {
            features: pw_wc_zibal_settings.supports,
        },
    };

    registerPaymentMethod(pw_wc_zibal_gateway_block);
})();