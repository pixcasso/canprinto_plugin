jQuery(function ($) {
    $(document).ready(function () {

        $('.price.nasa-single-product-price').addClass('cpo-status-loading');

        // Greife auf alle Dropzone-Instanzen zu
        Dropzone.instances.forEach(function (dropzoneInstance) {
            
            // Erfolgshandler: Thumbnail manuell einfügen
            dropzoneInstance.on('success', function (file, response) {
                if (response && response.data && response.data.mockup_url) {
                    var mockupUrl = response.data.mockup_url;

                    // Das Dropzone-Preview-Element finden und das Bild manuell hinzufügen
                    var previewElement = $(file.previewElement);

                    // Hauptbild ersetzen
                    var productImage = $('.woocommerce-product-gallery .nasa-item-main-image-wrap .easyzoom.first a.woocommerce-main-image img');
                    productImage.attr('src', mockupUrl);
                    productImage.attr('srcset', mockupUrl);

                    // Hauptbild Link aktualisieren
                    var productImageLink = $('.woocommerce-product-gallery .nasa-item-main-image-wrap .easyzoom.first a.woocommerce-main-image');
                    productImageLink.attr('href', mockupUrl);
                    productImageLink.attr('data-o_href', mockupUrl);
                    productImageLink.attr('data-full_href', mockupUrl);

                    // Thumbnail ersetzen
                    var productThumb = $('.product-thumbnails .active-thumbnail img');
                    productThumb.attr('src', mockupUrl);
                    productThumb.attr('srcset', mockupUrl);

                    // Mockup-URL als verstecktes Input-Element hinzufügen, um es an den Warenkorb zu übergeben
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'cpo_mockup_url',
                        value: mockupUrl
                    }).appendTo('form.cart');
                } else {
                    console.error('Fehlerhafte Antwort beim Hochladen des Bildes:', response);
                }
            });

            // Datei entfernen: Entferne auch das zugehörige Thumbnail
            dropzoneInstance.on('removedfile', function (file) {
                console.log('Datei entfernt:', file);
                $(dropzoneInstance.element).find('img.canprinto-thumbnail').prop('hidden', false); // Entferne alle Thumbnails

                // Das ursprüngliche Bild und Thumbnail wiederherstellen (falls gespeichert)
                var originalImageUrl = $('.woocommerce-product-gallery .nasa-item-main-image-wrap .easyzoom.first a.woocommerce-main-image img').data('large_image');
                if (originalImageUrl) {
                    var productImage = $('.woocommerce-product-gallery .nasa-item-main-image-wrap .easyzoom.first a.woocommerce-main-image img');
                    productImage.attr('src', originalImageUrl);
                    productImage.attr('srcset', originalImageUrl);

                    // Hauptbild Link aktualisieren
                    var productImageLink = $('.woocommerce-product-gallery .nasa-item-main-image-wrap .easyzoom.first a.woocommerce-main-image');
                    productImageLink.attr('href', originalImageUrl);
                    productImageLink.attr('data-o_href', originalImageUrl);
                    productImageLink.attr('data-full_href', originalImageUrl);

                    // Thumbnail ersetzen
                    var productThumb = $('.product-thumbnails .active-thumbnail img');
                    productThumb.attr('src', originalImageUrl);
                    productThumb.attr('srcset', originalImageUrl);
                }
            });
        });

        // Produkt-ID abrufen und AJAX zum Laden der Sorten-Optionen starten
        var productId = $('input[name="product_id"]').val();
        if (productId) {
            // AJAX-Anfrage zum Abrufen der Sorten-Optionen
             $.ajax({
                url: canprinto_ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: 'load_sorten_options',
                    product_id: productId
                },
                beforeSend: function () {
                    // Füge die Klasse "cpo-status-loading" hinzu
                    $('#cpo_options_wrapper').addClass('cpo-status-loading');
                    $('button[name="add-to-cart"], button.nasa-buy-now').prop('disabled', true);
                },
                success: function (response) {
                    if (response.success) {
                        // Füge den HTML-Inhalt zusätzlich in das Element mit der ID cpo_options_wrapper ein
                        $('#cpo_options_wrapper').append(response.data.html);
                        if (response.data.price) {
                            jQuery('#cpo_custom_price').val(response.data.price);
                            jQuery('.woocommerce-Price-amount bdi').text(response.data.price + ' €');
                        }
                    } else {
                        console.error('Fehler beim Abrufen der Optionen:', response.data);
                    }

                },
                error: function (xhr, status, error) {
                    console.error('AJAX-Fehler:', error);
                },
                complete: function () {
                    // Entferne die Klasse "cpo-status-loading"
                    $('#cpo_options_wrapper, .price.nasa-single-product-price').removeClass('cpo-status-loading');
                    $('.price.nasa-single-product-price span, .price.nasa-single-product-price small').css('display', 'block');
                    $('button[name="add-to-cart"], button.nasa-buy-now').prop('disabled', false);
                }
            }); 
        }

        // 'Click'-Event statt 'submit'-Event verwenden, um sicherzustellen, dass die Daten rechtzeitig hinzugefügt werden
        $('.single_add_to_cart_button').on('click', function (e) {
            //e.preventDefault(); // Verhindere das Standardverhalten des Formulars

            // Hole die Labels der ausgewählten Optionen aus dem cpo_options_wrapper
            let additionalOptions = {};
            $('#cpo_options_wrapper select').each(function () {
                var selectedOption = $(this).find('option:selected');
                additionalOptions[$(this).attr('name')] = selectedOption.text();
            });

            // Konsolenausgabe zur Überprüfung der zusätzlichen Optionen
            console.log('Zusätzliche Optionen, die dem Formular hinzugefügt werden:', additionalOptions);

            // Füge die zusätzlichen Daten als verstecktes Input-Element hinzu
            $('<input>').attr({
                type: 'hidden',
                name: 'cpo_custom_options',
                value: JSON.stringify(additionalOptions)
            }).appendTo('form.cart');

            /* Füge die Mockup-URL als verstecktes Input-Element hinzu, wenn vorhanden
            var mockupUrl = $('input[name="cpo_mockup_url"]').val();
            if (mockupUrl) {
                $('<input>').attr({
                    type: 'hidden',
                    name: 'cpo_mockup_url',
                    value: mockupUrl
                }).appendTo('form.cart');
            } */
        });

    }); // end document ready
});

// Funktion, die auf das Ändern eines "select"-Elements reagiert und Pods-Daten verarbeitet
function wmdOptionOnChange(event, podsData) {
    // Füge die Klasse "cpo-status-loading" hinzu
    jQuery('#cpo_options_wrapper, .price.nasa-single-product-price').addClass('cpo-status-loading');
    jQuery('button[name="add-to-cart"], button.nasa-buy-now').prop('disabled', true);
    // Das auslösende Element ermitteln
    const selectElement = event ? event.target : window.event.target;
    
    // Label und Wert des ausgewählten Elements abrufen
    const label = selectElement.getAttribute('aria-label') || selectElement.name || 'Unbekanntes Feld';
    const value = selectElement.value;
    
    // Konsolenausgabe im gewünschten Format
    console.log(`Change: ${label}, ${value}`);

    // Pods-Daten in der Konsole ausgeben
    console.log('Pods-Daten:', podsData);
    console.log('Selected Values:', getSelectedValues(podsData));
    selVal = getSelectedValues(podsData);
    // Überprüfen, ob das AJAX-Objekt verfügbar ist
    if (typeof canprinto_ajax_object === 'undefined') {
        console.error('canprinto_ajax_object ist nicht definiert. Stellen Sie sicher, dass wp_localize_script korrekt verwendet wurde.');
        return;
    }

    // Alle Elemente mit der Klasse "cpo-wmd-option" entfernen
    jQuery('.cpo-wmd-option').remove();


    // Zusätzlicher AJAX-Aufruf an eigenen Server zum Abrufen des HTMLs
    jQuery.ajax({
        url: canprinto_ajax_object.ajax_url, // Eigener Server-Endpunkt
        type: 'POST',
        data: {
            action: 'get_price_and_options',
            pods_data: podsData,
            product_link: podsData.wmd_product_link,
            selected_values: selVal
        },
        success: function (externalResponse) {
            console.log('Externer AJAX-Erfolg:', externalResponse);
            // Hier kannst du das erhaltene HTML auswerten, ähnlich wie in Get_WMD_Options
            if (externalResponse.success && externalResponse.data) {
                // Beispiel: Ausgabe des HTML-Inhalts
                console.log('Erhaltenes HTML:', externalResponse.data);
                // Neue Optionen zusätzlich in das Element mit der ID cpo_options_wrapper einfügen
                jQuery('#cpo_options_wrapper').append(externalResponse.data.html);
                // Den Preis im versteckten Eingabefeld setzen
                if (externalResponse.data.price) {
                    jQuery('#cpo_custom_price').val(externalResponse.data.price);
                    jQuery('.woocommerce-Price-amount bdi').text(externalResponse.data.price + ' €');
                }
                
            }
        },
        error: function (xhr, status, error) {
            console.error('Externer AJAX-Fehler:', error);
        },
        complete: function () {
            // Entferne die Klasse "cpo-status-loading"
            jQuery('#cpo_options_wrapper, .price.nasa-single-product-price').removeClass('cpo-status-loading');
            jQuery('button[name="add-to-cart"], button.nasa-buy-now').prop('disabled', false);
        }
    });
}

function getSelectedValues(podsData) {
    let selectedValues = [];

    // feste Eingabe für Berechnung
    selectedValues.push({
        id: 'cmd_calc',
        value: 'Berechnen'
    });

    // alle Pods-Feld-IDs durchlaufen
    podsData.wmd_option_id.forEach(function (optionName) {
        const element = document.querySelector(`[name="${optionName}"]`);
        if (!element) return;

        const tag = element.tagName.toLowerCase();

        let value = '';
        if (tag === 'select') {
            const selectedOption = element.options[element.selectedIndex];
            value = selectedOption ? selectedOption.value : '';
        } else if (tag === 'input') {
            value = element.value;
        }

        selectedValues.push({
            id: optionName,
            value: value
        });
    });

    return selectedValues;
}

