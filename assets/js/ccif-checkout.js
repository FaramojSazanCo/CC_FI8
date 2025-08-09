jQuery(function($) {
    'use strict';

    if (typeof ccifData === 'undefined' || !ccifData.cities) {
        console.error('CCIF Iran Checkout: City data is not available.');
        return;
    }
    var cities = ccifData.cities;

    /**
     * Toggles visibility of real/legal person fields.
     */
    function togglePersonFields() {
        var personType = $('#billing_person_type').val();
        var $realPersonWrapper = $('.ccif-real-person-fields-wrapper');
        var $legalPersonWrapper = $('.ccif-legal-person-fields-wrapper');

        if (personType === 'real') {
            $legalPersonWrapper.slideUp(250);
            $realPersonWrapper.slideDown(350);
        } else if (personType === 'legal') {
            $realPersonWrapper.slideUp(250);
            $legalPersonWrapper.slideDown(350);
        } else {
            $realPersonWrapper.slideUp(250);
            $legalPersonWrapper.slideUp(250);
        }
    }

    /**
     * Populates the custom city dropdown based on the custom state dropdown.
     */
    function populateCustomCities() {
        var state = $('#billing_custom_state').val();
        var $cityField = $('#billing_custom_city');
        var originalCityVal = $('#billing_city').val(); // Get value from original hidden field

        $cityField.empty().append('<option value="">ابتدا استان را انتخاب کنید</option>');

        if (state && cities[state]) {
            $.each(cities[state], function(index, cityName) {
                $cityField.append($('<option>', {
                    value: cityName,
                    text: cityName,
                    selected: cityName === originalCityVal
                }));
            });
        }
        // After populating, ensure the custom city's value is synced to the original
        $cityField.trigger('change');
    }

    // --- Synchronization Logic ---

    // 1. When the VISIBLE custom state changes...
    $('body').on('change', '#billing_custom_state', function() {
        var selectedState = $(this).val();
        // a. Update the HIDDEN original state field
        $('#billing_state').val(selectedState);
        // b. Manually trigger 'change' on the original field to make WC's AJAX work
        $('#billing_state').trigger('change');
        // c. Populate our custom city dropdown
        populateCustomCities();
    });

    // 2. When the VISIBLE custom city changes...
    $('body').on('change', '#billing_custom_city', function() {
        var selectedCity = $(this).val();
        // a. Update the HIDDEN original city field
        $('#billing_city').val(selectedCity);
        // b. Manually trigger 'change' on the original field
        $('#billing_city').trigger('change');
    });

    // --- Initial page load logic ---

    // Set initial custom state value from the original hidden field (in case of validation error reload)
    $('#billing_custom_state').val($('#billing_state').val());
    // Trigger the change handler to populate cities on load
    $('#billing_custom_state').trigger('change');

    // Person fields
    togglePersonFields();
    $('body').on('change', '#billing_person_type', togglePersonFields);
});
