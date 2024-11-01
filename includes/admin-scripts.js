jQuery(document).ready(function($) {
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'get_si_script_status'
        },
        success: function(response) {
            if (response === 'true') {
                $('input[name="enable-si-script"]').prop('checked', true);
            }
        }
    });

    $('input[name="enable-si-script"]').on('change', function() {
        var isChecked = $(this).is(':checked');
        $.ajax({
            url: myAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'update_si_script_status',
                status: isChecked,
                security: myAjax.nonce,
            },
        });
    });

    $('#saveButton').on('click', function() {
        var scriptTagValue = $('#shipinsure_script_tag').val();
        var scriptVersionValue = $('#shipinsure_script_version').val();
        alert('Script tag information has been updated.');
        $.ajax({
            url: myAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'update_si_script_tag',
                script_tag: scriptTagValue,
                script_version: scriptVersionValue,
                security: myAjax.nonce,
            },
        });
    });

    $('#saveStagingSettings').on('click', function() {
        var isStaging = $('input[name="shipinsure_is_staging_site"]:checked').val();
        var productionURL = $('#shipinsure_production_url').val();

        $.ajax({
            url: myAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'update_si_site_settings',
                is_staging: isStaging,
                production_url: productionURL,
                security: myAjax.nonce,
            },
        });
    });

    function toggleProductionUrlContainer() {
        if ($('input[name="shipinsure_is_staging_site"]:checked').val() == 'true') {
            $('.shipinsure_production_url_container').show();
        } else {
            $('.shipinsure_production_url_container').hide();
        }
            
    }

    toggleProductionUrlContainer();

    $('input[name="shipinsure_is_staging_site"]').change(function() {
        toggleProductionUrlContainer();
    });

});