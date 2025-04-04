jQuery(document).ready(function ($) {
    console.log("drive-go-modal.js loaded!");
    let hoverTimeout;

    $('.wc-block-product').hover(
        function () {
            let productId = $(this).data('wc-context').productId;

            if (!productId) return;

            hoverTimeout = setTimeout(function () {
                $.post(driveGoAjax.ajaxurl, {
                    action: 'drive_go_load_product_details',
                    product_id: productId
                }, function (response) {
                    if (response.success) {
                        let bookingButton = response.data.availability === 'Yes'
                            ? `<a href="${response.data.product_url}" class="drive-go-booking-btn">Book Now</a>`
                            : `<p style="color: red; font-weight: bold;">Not available for booking</p>`;

                        let attributesHtml = `
                            <div class="drive-go-attributes">
                                <div><strong>Engine Type:</strong> ${response.data.engine_type}</div>
                                <div><strong>Fuel:</strong> ${response.data.fuel}</div>
                                <div><strong>Gearbox:</strong> ${response.data.gearbox}</div>
                                <div><strong>Seats:</strong> ${response.data.seats}</div>
                            </div>`;

                        $('#drive-go-product-details').html(`
                            <img src="${response.data.image}" alt="${response.data.title}" style="width: 100%;">
                            <h2 style="margin: 0;">${response.data.title}</h2>
                            <p><strong>Price:</strong> ${response.data.price}</p>
                            ${attributesHtml}
                            <p>${response.data.description}</p>
                            ${bookingButton}
                        `);

                        $('#drive-go-product-modal')
                            .css("display", "flex")
                            .hide()
                            .fadeIn();
                    }
                });
            }, 2000);
        },
        function () {
            clearTimeout(hoverTimeout);
        }
    );

    $('.drive-go-close').click(function () {
        $('#drive-go-product-modal').fadeOut();
    });

    $(document).on('click', function (event) {
        if ($(event.target).is('#drive-go-product-modal')) {
            $('#drive-go-product-modal').fadeOut();
        }
    });

    $(".wc-block-product-template__responsive .wp-block-group a.button").text("Booking Now");
    $(".wc-block-components-button__text").text("Find Car");

    $("input#keyword").keyup(function () {
        if ($(this).val().length > 2) {
            $("#datafetch").show();
        } else {
            $("#datafetch").hide();
        }
    });
});
