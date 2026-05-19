(function($){
'use strict';

// Warten bis DOM ready
$(function(){

    const state = {
        step: 1,
        service: null, servicePrice: 0, serviceName: '',
        isUploadFlow: false,
        date: null, slot: null,
        uploadedFilePath: null, uploadedFileName: null, mixNotes: '',
        name: '', email: '', phone: '', artistName: '', address: '', plz: '', city: '', message: '',
        paymentMethod: null,
        couponCode: null,
        discountAmount: 0,
        finalPrice: 0,
        userId: window.studiobookCurrentUserId || 0,
    };

    const cal = {
        year: new Date().getFullYear(), month: new Date().getMonth() + 1,
        dayData: {}, loading: false,
    };

    const MONTHS_DE = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
    let stripe, stripeElements, stripeCard, paypalRendered = false;

    // ── Navigation ──
    function showStep(n) {
        state.step = n;
        $('.studiobook-step').hide();

        if (n === 1) {
            $('[data-step="1"]').show();

    // Back from payment goes to step 3
    $(document).on('click', '[data-back="4"]', function() {
        showStep(3);
    });
        } else if (n === 2) {
            const v = state.isUploadFlow ? 'upload' : 'calendar';
            $('[data-step="2"][data-variant="' + v + '"]').show();
            if (!state.isUploadFlow) initCalendar();
        } else if (n === 4) {
            // Auth Gate: wenn nicht eingeloggt, zuerst Login/Register zeigen
            if (!window.studiobookIsLoggedIn) {
                $('[data-step="4"][data-variant="auth-gate"]').show();
            } else {
                showPaymentStep();
            }
        } else if (n === 41) {
            // interner Step: direkt zur Zahlung (nach Login)
            showPaymentStep();
        } else {
            $('[data-step="' + n + '"]').not('[data-variant]').show();
        }
    }

    function showPaymentStep() {
        $('.studiobook-step').hide();
        $('[data-step="4"]').not('[data-variant]').show();
        buildReviewBlocks();
        buildSummary();
        if (state.isUploadFlow) {
            $('.sb-cash-option').hide();
            $('#sb-cancel-policy-wrap').hide();
        } else {
            $('.sb-cash-option').show();
            $('#sb-cancel-policy-wrap').show();
        }
    }

    $('[data-step="1"]').show();

    // Back from payment goes to step 3
    $(document).on('click', '[data-back="4"]', function() {
        showStep(3);
    });

    // Zurück-Buttons (data-back)
    $(document).on('click', '[data-back]', function () {
        showStep(parseInt($(this).data('back')));
    });

    // Ändern-Buttons in Review-Blocks
    $(document).on('click', '.sb-review-change', function () {
        showStep(parseInt($(this).data('back-step')));
    });

    // ── Step 1 ──
    $('#studiobook-step1-next').on('click', function () {
        const sel = $('input[name="studiobook_service"]:checked');
        if (!sel.length) { alert('Bitte wähle einen Service.'); return; }
        state.service      = sel.val();
        state.servicePrice = parseFloat(sel.data('price')) || 0;
        state.finalPrice   = state.servicePrice;
        state.serviceName  = sel.data('name');
        state.isUploadFlow = (state.service === 'mixing_mastering');
        showStep(2);
    });

    // ── Kalender ──
    function initCalendar() { loadMonthData(cal.year, cal.month); }

    function loadMonthData(year, month) {
        if (cal.loading) return;
        cal.loading = true;
        $('#sb-cal-title').text(MONTHS_DE[month-1] + ' ' + year);
        $('#sb-cal-grid').html('<div class="sb-cal-loading">Lade&#8230;</div>');
        $.post(studiobookAjax.ajaxurl, {
            action: 'studiobook_get_month', nonce: studiobookAjax.nonce, year: year, month: month,
        }, function (res) {
            cal.loading = false;
            if (!res.success) return;
            cal.dayData = res.data.days; cal.year = year; cal.month = month;
            renderGrid(year, month);
        });
    }

    function renderGrid(year, month) {
        const dim = new Date(year, month, 0).getDate();
        let fd    = new Date(year, month-1, 1).getDay();
        fd        = fd === 0 ? 6 : fd-1;
        const today = new Date().toISOString().slice(0,10);
        let html  = '';
        for (let i = 0; i < fd; i++) html += '<div class="sb-cal-day empty"></div>';
        for (let d = 1; d <= dim; d++) {
            const date   = year + '-' + String(month).padStart(2,'0') + '-' + String(d).padStart(2,'0');
            const status = cal.dayData[date] || 'open';
            const isPast = date < today;
            let cls = 'sb-cal-day';
            if (isPast || status === 'past') cls += ' past';
            else if (status === 'full')      cls += ' full';
            else if (status === 'partial')   cls += ' partial';
            else                              cls += ' open';
            if (date === state.date) cls += ' selected';
            const ok = !isPast && status !== 'full' && status !== 'past';
            html += '<div class="' + cls + '"' + (ok ? ' data-date="' + date + '"' : '') + '>' + d + '</div>';
        }
        $('#sb-cal-grid').html(html);
    }

    $('#sb-cal-prev').on('click', function () {
        let m = cal.month-1, y = cal.year;
        if (m < 1) { m = 12; y--; }
        state.date = null; state.slot = null; $('#studiobook-slots').html('');
        loadMonthData(y, m);
    });
    $('#sb-cal-next').on('click', function () {
        let m = cal.month+1, y = cal.year;
        if (m > 12) { m = 1; y++; }
        state.date = null; state.slot = null; $('#studiobook-slots').html('');
        loadMonthData(y, m);
    });

    $(document).on('click', '.sb-cal-day.open, .sb-cal-day.partial', function () {
        state.date = $(this).data('date'); state.slot = null;
        $('.sb-cal-day').removeClass('selected'); $(this).addClass('selected');
        $('#studiobook-slots').html('<p class="loading">Lade Zeiten&#8230;</p>');
        $.post(studiobookAjax.ajaxurl, {
            action: 'studiobook_get_slots', nonce: studiobookAjax.nonce, date: state.date,
        }, function (res) {
            if (!res.success) { $('#studiobook-slots').html('<p class="error">' + res.data.message + '</p>'); return; }
            const d = new Date(state.date + 'T00:00:00');
            const label = d.toLocaleDateString('de-DE', {weekday:'long',day:'2-digit',month:'long',year:'numeric'});
            let html = '<p class="slots-label">' + label + '</p><div class="slots-grid">';
            res.data.slots.forEach(function(s) {
                const cls = s.available ? 'slot available' : 'slot taken';
                const tip = (!s.available && s.reason === 'too_soon') ? ' title="Zu kurzfristig (mind. 24h vorher)"' : '';
                html += '<button class="' + cls + '" data-time="' + s.time + '"' + (s.available ? '' : ' disabled') + tip + '>' + s.time + ' Uhr</button>';
            });
            html += '</div>';
            $('#studiobook-slots').html(html);
        });
    });

    $(document).on('click', '.slot.available', function () {
        $('.slot').removeClass('selected'); $(this).addClass('selected');
        state.slot = $(this).data('time');
    });

    $('#studiobook-step2-next').on('click', function () {
        if (!state.date) { alert('Bitte wähle ein Datum.'); return; }
        if (!state.slot) { alert('Bitte wähle eine Uhrzeit.'); return; }
        showStep(3);
    });

    // ── Upload – ein einziger sauberer Handler, kein Delegation ──
    var uploadHandlerBound = false;

    function bindUploadHandlers() {
        if (uploadHandlerBound) return;
        uploadHandlerBound = true;

        var fileInput = document.getElementById('sb-file-input');
        var uploadArea = document.getElementById('sb-upload-area');
        var uploadBtn  = document.getElementById('sb-upload-btn');
        var removeBtn  = document.getElementById('sb-upload-remove');

        if (!fileInput || !uploadArea) return;

        // Klick auf Upload-Bereich
        uploadArea.addEventListener('click', function(e) {
            if (removeBtn && removeBtn.contains(e.target)) return;
            if (state.uploadedFilePath) return;
            fileInput.click();
        });

        // Datei gewählt
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) handleFile(this.files[0]);
        });

        // Drag & Drop
        uploadArea.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('drag-over'); });
        uploadArea.addEventListener('dragleave', function(e) { e.preventDefault(); this.classList.remove('drag-over'); });
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault(); this.classList.remove('drag-over');
            var files = e.dataTransfer.files;
            if (files && files[0]) handleFile(files[0]);
        });

        // Entfernen
        if (removeBtn) {
            removeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                state.uploadedFilePath = null; state.uploadedFileName = null;
                fileInput.value = '';
                document.getElementById('sb-upload-done').style.display = 'none';
                document.getElementById('sb-upload-inner').style.display = 'flex';
            });
        }
    }

    function handleFile(file) {
        var maxB = 200 * 1024 * 1024;
        if (file.size > maxB) { alert('Datei zu groß (max. 200MB).'); return; }
        var allowed = ['mp3','wav','flac','aiff','aif','ogg','m4a','zip','rar'];
        var ext = file.name.split('.').pop().toLowerCase();
        if (allowed.indexOf(ext) === -1) { alert('Format nicht erlaubt.'); return; }

        document.getElementById('sb-upload-inner').style.display = 'none';
        document.getElementById('sb-upload-done').style.display = 'none';
        document.getElementById('sb-upload-progress').style.display = 'block';
        document.getElementById('sb-progress-fill').style.width = '0%';

        var fd = new FormData();
        fd.append('action', 'studiobook_upload_file');
        fd.append('nonce', studiobookAjax.nonce);
        fd.append('file', file);

        var xhr = new XMLHttpRequest();
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                var p = Math.round(e.loaded / e.total * 100);
                document.getElementById('sb-progress-fill').style.width = p + '%';
                document.getElementById('sb-progress-label').textContent = p + '%';
            }
        });
        xhr.addEventListener('load', function() {
            document.getElementById('sb-upload-progress').style.display = 'none';
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) {
                    state.uploadedFilePath = res.data.file_path;
                    state.uploadedFileName = res.data.file_name;
                    document.getElementById('sb-done-filename').textContent = res.data.file_name;
                    document.getElementById('sb-upload-done').style.display = 'flex';
                } else {
                    alert(res.data.message);
                    document.getElementById('sb-upload-inner').style.display = 'flex';
                }
            } catch(e) {
                alert('Upload fehlgeschlagen.');
                document.getElementById('sb-upload-inner').style.display = 'flex';
            }
        });
        xhr.addEventListener('error', function() {
            document.getElementById('sb-upload-progress').style.display = 'none';
            document.getElementById('sb-upload-inner').style.display = 'flex';
            alert('Upload fehlgeschlagen.');
        });
        xhr.open('POST', studiobookAjax.ajaxurl);
        xhr.send(fd);
    }

    $('#studiobook-step2b-next').on('click', function () {
        if (!state.uploadedFilePath) { alert('Bitte lade deine Datei hoch.'); return; }
        state.mixNotes = $('#sb-mix-notes').val().trim();
        showStep(3);
    });

    // Upload-Handler binden wenn Step 2 Upload sichtbar wird
    var origShowStep = showStep;
    showStep = function(n) {
        origShowStep(n);
        if (n === 2 && state.isUploadFlow) {
            setTimeout(bindUploadHandlers, 100);
        }
    };

    // ── Step 3 ──
    $('#studiobook-step3-next').on('click', function () {
        state.name    = $('#sb-name').val().trim();
        state.email   = $('#sb-email').val().trim();
        state.phone      = $('#sb-phone').val().trim();
        state.artistName = $('#sb-artist-name').val().trim();
        state.address = $('#sb-address').val().trim();
        state.plz     = $('#sb-plz').val().trim();
        state.city    = $('#sb-city').val().trim();
        state.message = $('#sb-message').val().trim();
        if (!state.name)    { alert('Bitte gib deinen Namen ein.'); return; }
        if (!state.email || !state.email.includes('@')) { alert('Bitte gib eine gültige E-Mail ein.'); return; }
        if (!state.phone)   { alert('Bitte gib deine Telefonnummer ein.'); return; }
        if (!state.address) { alert('Bitte gib deine Adresse ein.'); return; }
        if (!state.plz)     { alert('Bitte gib deine PLZ ein.'); return; }
        if (!state.city)    { alert('Bitte gib deinen Ort ein.'); return; }
        showStep(4);
    });

    // ── Review Blocks ──
    function buildReviewBlocks() {
        // Buchungsblock
        var bookingHtml = '<div class="sb-review-row"><span>' + state.serviceName + '</span></div>';
        if (!state.isUploadFlow && state.date) {
            var d = new Date(state.date + 'T00:00:00');
            var dateStr = d.toLocaleDateString('de-DE', {weekday:'long', day:'2-digit', month:'2-digit', year:'numeric'});
            bookingHtml += '<div class="sb-review-row"><span>' + dateStr + '</span></div>';
            if (state.slot) bookingHtml += '<div class="sb-review-row"><span>' + state.slot + ' Uhr</span></div>';
        }
        if (state.isUploadFlow && state.uploadedFileName) {
            bookingHtml += '<div class="sb-review-row"><span>&#128206; ' + state.uploadedFileName + '</span></div>';
        }
        $('#sb-review-booking').html(bookingHtml);

        // Kontaktblock
        var contactHtml =
            '<div class="sb-review-row"><span>' + state.name + '</span></div>' +
            '<div class="sb-review-row"><span>' + state.email + '</span></div>' +
            '<div class="sb-review-row"><span>' + state.phone + '</span></div>' +
            '<div class="sb-review-row"><span>' + state.address + ', ' + state.plz + ' ' + state.city + '</span></div>';
        $('#sb-review-contact').html(contactHtml);
    }

    // ── Summary ──
    function buildSummary() {
        var tax   = window.studiobookTax || {enabled:false, rate:0};
        var net   = state.servicePrice;
        var taxA  = tax.enabled ? Math.round(net * tax.rate / 100 * 100) / 100 : 0;
        var gross = net + taxA;
        var rows  = '';
        if (net > 0) {
            rows += '<div class="summary-row"><span>Netto</span><strong>' + net.toFixed(2) + ' \u20ac</strong></div>';
            if (tax.enabled) rows += '<div class="summary-row"><span>MwSt. ' + tax.rate + '%</span><strong>' + taxA.toFixed(2) + ' \u20ac</strong></div>';
            rows += '<div class="summary-row summary-total"><span>Gesamt</span><strong>' + gross.toFixed(2) + ' \u20ac</strong></div>';
        } else {
            rows += '<div class="summary-row"><span>Preis</span><strong>Auf Anfrage</strong></div>';
        }
        $('#studiobook-summary').html(rows);
    }


    // ── Gutschein ──
    $('#sb-coupon-btn').on('click', async function(){
        var btn  = $(this).prop('disabled', true).text('Prüfe…');
        var code = $('#sb-coupon-input').val().trim().toUpperCase();
        if (!code) { btn.prop('disabled',false).text('Einlösen'); return; }

        var res = await $.post(studiobookAjax.ajaxurl, {
            action: 'studiobook_validate_coupon',
            nonce:  studiobookAjax.nonce,
            code:   code,
            price:  state.finalPrice || state.servicePrice,
        });

        if (res.success) {
            state.couponCode     = res.data.code;
            state.discountAmount = res.data.discount;
            state.finalPrice     = res.data.new_price;
            $('#sb-coupon-msg').css('color','#28a745').text('✓ ' + res.data.label + ' angewendet!');
            $('#sb-coupon-input').prop('disabled', true);
            buildSummary();
        } else {
            $('#sb-coupon-msg').css('color','#ff4d4d').text(res.data.message);
        }
        btn.prop('disabled',false).text('Einlösen');
    });

    // Enter in coupon field
    $('#sb-coupon-input').on('keypress', function(e){
        if (e.which === 13) $('#sb-coupon-btn').click();
    });

    function checkboxesOk() {
        if (!$('#sb-privacy-check').is(':checked'))  { $('#sb-pay-error').text('Bitte stimme der Datenschutzerklärung zu.'); return false; }
        if (!$('#sb-agb-check').is(':checked'))       { $('#sb-pay-error').text('Bitte stimme den AGB zu.'); return false; }
        if (!$('#sb-widerruf-check').is(':checked'))  { $('#sb-pay-error').text('Bitte bestätige die Widerrufsbelehrung.'); return false; }
        $('#sb-pay-error').text(''); return true;
    }

    // ── Zahlungsmethode ──
    $('input[name="payment_method"]').on('change', function () {
        state.paymentMethod = $(this).val();
        $('.payment-section').hide();
        $('#sb-submit-btn').hide();
        if (state.paymentMethod === 'stripe') {
            $('#stripe-section').show();
            $('#sb-submit-btn').show().text('Jetzt bezahlen & buchen');
            initStripe();
        } else if (state.paymentMethod === 'paypal') {
            $('#paypal-section').show();
            if (!paypalRendered) initPayPal();
        } else if (state.paymentMethod === 'cash') {
            $('#cash-section').show();
            $('#sb-submit-btn').show().text('Jetzt verbindlich buchen');
        }
    });

    // ── Submit ──
    $('#sb-submit-btn').on('click', async function () {
        if (!checkboxesOk()) return;
        if (state.paymentMethod === 'cash') await doCashBooking();
        else if (state.paymentMethod === 'stripe') await doStripeBooking($(this));
    });

    // ── Test-Buchung ──
    $('#sb-test-booking-btn').on('click', async function () {
        var btn = $(this).prop('disabled', true).text('Simuliere…');
        var res = await $.post(studiobookAjax.ajaxurl, getPayload({action:'studiobook_test_booking'}));
        if (res.success) finishBooking(res.data.booking_id);
        else { alert(res.data.message); btn.prop('disabled', false).text('&#128295; Test-Buchung simulieren'); }
    });

    async function doCashBooking() {
        var btn = $('#sb-submit-btn').prop('disabled',true).text('Buche…');
        var res = await $.post(studiobookAjax.ajaxurl, getPayload({action:'studiobook_cash_booking'}));
        if (res.success) finishBooking(res.data.booking_id);
        else { $('#sb-pay-error').text(res.data.message); btn.prop('disabled',false).text('Jetzt verbindlich buchen'); }
    }

    async function doStripeBooking(btn) {
        btn.prop('disabled',true).text('Verarbeite…');
        var intentRes = await $.post(studiobookAjax.ajaxurl, {
            action:'studiobook_stripe_create_intent', nonce:studiobookAjax.nonce,
            price:state.finalPrice || state.servicePrice, service:state.service, date:state.date||'', slot:state.slot||'',
        });
        if (!intentRes.success) { $('#sb-pay-error').text(intentRes.data.message); btn.prop('disabled',false).text('Jetzt bezahlen & buchen'); return; }

        var result = await stripe.confirmCardPayment(intentRes.data.client_secret, {
            payment_method: {card:stripeCard, billing_details:{name:state.name, email:state.email}}
        });
        if (result.error) { $('#sb-pay-error').text(result.error.message); btn.prop('disabled',false).text('Jetzt bezahlen & buchen'); return; }

        var confirmRes = await $.post(studiobookAjax.ajaxurl, getPayload({
            action:'studiobook_stripe_confirm', payment_intent_id:result.paymentIntent.id,
        }));
        if (confirmRes.success) finishBooking(confirmRes.data.booking_id);
        else { $('#sb-pay-error').text(confirmRes.data.message); btn.prop('disabled',false).text('Jetzt bezahlen & buchen'); }
    }

    function initStripe() {
        if (stripe || !window.studiobookStripeKey) return;
        stripe = Stripe(window.studiobookStripeKey);
        stripeElements = stripe.elements();
        stripeCard = stripeElements.create('card', {
            style:{base:{color:'#fff','::placeholder':{color:'#888'}}}
        });
        stripeCard.mount('#stripe-card-element');
    }

    function initPayPal() {
        if (!window.paypal) return;
        paypalRendered = true;
        paypal.Buttons({
            createOrder: async function () {
                if (!checkboxesOk()) throw new Error('checks');
                var res = await $.post(studiobookAjax.ajaxurl, {
                    action:'studiobook_paypal_create_order', nonce:studiobookAjax.nonce,
                    price:state.servicePrice, service_name:state.serviceName,
                });
                if (!res.success) { alert(res.data.message); throw new Error(res.data.message); }
                return res.data.order_id;
            },
            onApprove: async function (data) {
                var res = await $.post(studiobookAjax.ajaxurl, getPayload({
                    action:'studiobook_paypal_capture', order_id:data.orderID,
                }));
                if (res.success) finishBooking(res.data.booking_id);
                else alert(res.data.message);
            },
            onError: function () { alert('PayPal Fehler. Bitte versuche es erneut.'); }
        }).render('#paypal-button-container');
    }

    function getPayload(extra) {
        return Object.assign({
            nonce: studiobookAjax.nonce,
            service: state.service, date: state.date||'', slot: state.slot||'',
            name: state.name, email: state.email, phone: state.phone, artist_name: state.artistName,
            address: state.address, plz: state.plz, city: state.city,
            message: state.isUploadFlow ? state.mixNotes : state.message,
            price: state.finalPrice || state.servicePrice,
            user_id: state.userId || 0,
            coupon_code: state.couponCode || '',
            discount_amount: state.discountAmount || 0,
            file_path: state.uploadedFilePath||'', file_name: state.uploadedFileName||'',
        }, extra);
    }

    function finishBooking(bookingId) {
        $('#sb-confirm-email').text(state.email);
        var t = window.studiobookTexts || {};
        $('#sb-success-subtitle').text(state.isUploadFlow ? t.successMix : t.successSession);
        // Booking-ID für Post-Booking Auth setzen
        if (bookingId) $('#sb-post-booking-id').val(bookingId);
        // E-Mail vorausfüllen
        if (state.email) {
            $('#sb-reg-email').val(state.email);
        }
        showStep(5);
    }

});
})(jQuery);
