jQuery(function($) {
    'use strict';

    if (typeof ccifData === 'undefined' || !ccifData.cities) {
        console.error('CCIF Iran Checkout: City data is not available.');
        return;
    }
    var cities = ccifData.cities;

    /**
     * Creates the card-based layout for the checkout form.
     * This function wraps fields into styled boxes.
     */
    function createCheckoutLayout() {
        var $formContainer = $('.woocommerce-billing-fields .woocommerce-billing-fields__field-wrapper');
        if (!$formContainer.length) return; // Exit if the main container isn't found

        // Add a global wrapper for overall styling, if it doesn't already exist.
        if (!$formContainer.parent().hasClass('ccif-checkout-form')) {
            $formContainer.wrap('<div class="ccif-checkout-form"></div>');
        }

        // --- Card 1: Invoice Request ---
        var $invoiceField = $('#billing_invoice_request_field');
        if ($invoiceField.length && !$invoiceField.closest('.ccif-box').length) {
            // Add the "invoice-request-box" class to get the green background style from CSS
            $invoiceField.wrap('<div class="ccif-box invoice-request-box" id="ccif-invoice-box"></div>');
            // Removed "(اختیاری)" from the title as requested
            $('#ccif-invoice-box').prepend('<h2>درخواست صدور فاکتور رسمی</h2><p class="ccif-hint">در صورت نیاز به فاکتور رسمی، این گزینه را انتخاب و تمام اطلاعات خریدار را به دقت وارد نمایید. در غیر این صورت، تنها تکمیل اطلاعات ارسال کافی است.</p>');
        }

        // --- Card 2: Buyer Information ---
        var $buyerFields = $('#billing_person_type_field, #billing_first_name_field, #billing_last_name_field, #billing_national_code_field, #billing_company_name_field, #billing_economic_code_field, #billing_agent_first_name_field, #billing_agent_last_name_field');
        if ($buyerFields.length && !$buyerFields.first().closest('.ccif-box').length) {
            $buyerFields.wrapAll('<div class="ccif-box" id="ccif-buyer-info-box"><div class="ccif-buyer-fields-wrapper"></div></div>');
            $('#ccif-buyer-info-box').prepend('<h2 class="ccif-person-info-header">اطلاعات خریدار</h2>');

            // Wrap real person fields
            $('#billing_first_name_field, #billing_last_name_field, #billing_national_code_field').wrapAll('<div class="ccif-real-person-fields-wrapper" style="display: none;"></div>');
            // Use the standard hint class for better consistency
            $('#billing_national_code_field p.form-row').append('<span class="ccif-hint">۱۰ رقم بدون خط تیره</span>');

            // Wrap legal person fields
            $('#billing_company_name_field, #billing_economic_code_field, #billing_agent_first_name_field, #billing_agent_last_name_field').wrapAll('<div class="ccif-legal-person-fields-wrapper" style="display: none;"></div>');
        }

        // --- Card 3: Shipping Information ---
        var $shippingFields = $('#billing_custom_state_field, #billing_custom_city_field, #billing_address_1_field, #billing_postcode_field, #billing_phone_field');
        if ($shippingFields.length && !$shippingFields.first().closest('.ccif-box').length) {
            $shippingFields.wrapAll('<div class="ccif-box" id="ccif-shipping-info-box"></div>');
            $('#ccif-shipping-info-box').prepend('<h2 class="ccif-address-info-header">اطلاعات ارسال</h2>');
        }

        // --- Card 4: Order Notes ---
        // WooCommerce might render notes inside or outside the main billing form. We find it and move it.
        var $notesContainer = $('.woocommerce-additional-fields');
        // FIX: Added guard to prevent re-wrapping on checkout update
        if ($notesContainer.length && !$notesContainer.closest('.ccif-box').length) {
            // Move the whole container to the end of our main form for consistent styling
            $formContainer.parent().append($notesContainer);
            $notesContainer.wrap('<div class="ccif-box" id="ccif-notes-box"></div>');
            // Check if a title already exists, if not, add one.
            if (!$notesContainer.find('h3').length) {
                $notesContainer.prepend('<h2 class="ccif-order-notes-header">توضیحات تکمیلی</h2>');
            }
        }
    }

    /**
     * Toggles visibility of real/legal person fields based on selection.
     */
    function togglePersonFields() {
        var personType = $('#billing_person_type').val();
        var $realPersonWrapper = $('.ccif-real-person-fields-wrapper');
        var $legalPersonWrapper = $('.ccif-legal-person-fields-wrapper');

        if (personType === 'real') {
            $legalPersonWrapper.slideUp(200);
            $realPersonWrapper.slideDown(300);
        } else if (personType === 'legal') {
            $realPersonWrapper.slideUp(200);
            $legalPersonWrapper.slideDown(300);
        } else {
            $realPersonWrapper.slideUp(200);
            $legalPersonWrapper.slideUp(200);
        }
    }

    /**
     * Populates the custom city dropdown based on the custom state dropdown.
     */
    /**
     * Toggles the 'required' state of buyer info fields based on the invoice request checkbox
     * and the selected person type.
     */
    function toggleRequiredFields() {
        var isInvoiceRequested = $('#billing_invoice_request').is(':checked');
        var personType = $('#billing_person_type').val();
        var $personTypeField = $('#billing_person_type_field');
        var $realPersonFields = $('.ccif-real-person-fields-wrapper .form-row');
        var $legalPersonFields = $('.ccif-legal-person-fields-wrapper .form-row');

        // First, handle the person type field itself. It's required if an invoice is requested.
        $personTypeField.toggleClass('ccif-is-required', isInvoiceRequested);

        if (isInvoiceRequested) {
            // If invoice is requested, requirement depends on person type
            if (personType === 'real') {
                $realPersonFields.addClass('ccif-is-required');
                $legalPersonFields.removeClass('ccif-is-required');
            } else if (personType === 'legal') {
                $realPersonFields.removeClass('ccif-is-required');
                $legalPersonFields.addClass('ccif-is-required');
            } else {
                // No person type selected yet, so don't make any sub-fields required
                $realPersonFields.removeClass('ccif-is-required');
                $legalPersonFields.removeClass('ccif-is-required');
            }
        } else {
            // If invoice is not requested, nothing in this box is required.
            $personTypeField.removeClass('ccif-is-required');
            $realPersonFields.removeClass('ccif-is-required');
            $legalPersonFields.removeClass('ccif-is-required');
        }
    }

    function populateCustomCities() {
        var state = $('#billing_custom_state').val();
        var $cityField = $('#billing_custom_city');
        var originalCityVal = $('#billing_city').val();

        $cityField.empty().append('<option value="">ابتدا استان را انتخاب کنید</option>');

        if (state && cities[state]) {
            $.each(cities[state], function(index, cityName) {
                $cityField.append($('<option>', {
                    value: cityName,
                    text: cityName,
                    selected: cityName === originalCityVal
                }));
            });
            $cityField.val(originalCityVal).trigger('change');
        }
    }

    // --- Event Handlers ---
    $('body').on('change', '#billing_custom_state', function() {
        $('#billing_state').val($(this).val()).trigger('change');
        populateCustomCities();
    });

    $('body').on('change', '#billing_custom_city', function() {
        $('#billing_city').val($(this).val()).trigger('change');
    });

    $('body').on('change', '#billing_person_type, #billing_invoice_request', function() {
        togglePersonFields();
        toggleRequiredFields();
    });

    // --- Initial Page Load Logic ---
    // Use a small timeout to ensure all elements are rendered, especially during AJAX reloads.
    setTimeout(function() {
        // 1. Create the layout
        createCheckoutLayout();

        // 2. Set initial custom state value from the hidden original field
        var initialState = $('#billing_state').val();
        if (initialState) {
            $('#billing_custom_state').val(initialState);
        }

        // 3. Populate cities based on the initial state
        populateCustomCities();

        // 4. Trigger the toggles to set the initial correct view
        togglePersonFields();
        toggleRequiredFields();
    }, 100);

    // Also run on WooCommerce's 'update_checkout' event (e.g., after shipping calculation)
    $(document.body).on('updated_checkout', function() {
        createCheckoutLayout();
        var initialState = $('#billing_state').val();
        if (initialState) {
            $('#billing_custom_state').val(initialState);
        }
        populateCustomCities();
        togglePersonFields();
        toggleRequiredFields();
    });
});
