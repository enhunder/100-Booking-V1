(function($){
'use strict';

$(function(){

    // ── Auth Tabs ──
    $(document).on('click', '.sb-auth-tab', function(){
        const tab = $(this).data('tab');
        $('.sb-auth-tab').removeClass('active');
        $(this).addClass('active');
        $('.sb-auth-panel').removeClass('active');
        $('#sb-tab-' + tab).addClass('active');
    });

    // ── Forgot Password ──
    $(document).on('click', '#sb-forgot-btn', function(){
        $('.sb-auth-tab').removeClass('active');
        $('.sb-auth-panel').removeClass('active');
        $('#sb-tab-forgot').addClass('active');
    });

    $(document).on('click', '#sb-back-to-login', function(){
        $('.sb-auth-panel').removeClass('active');
        $('#sb-tab-login').addClass('active');
        $('.sb-auth-tab').removeClass('active');
        $('.sb-auth-tab[data-tab="login"]').addClass('active');
    });

    $(document).on('click', '#sb-forgot-submit-btn', async function(){
        const btn   = $(this).prop('disabled', true).text('Sende…');
        const email = $('#sb-forgot-email').val().trim();
        if (!email) { $('#sb-forgot-msg').text('Bitte E-Mail eingeben.'); btn.prop('disabled',false).text('Reset-Link senden'); return; }

        const res = await $.post(studiobookAjax.ajaxurl, {
            action: 'studiobook_forgot_password',
            nonce:  studiobookAjax.nonce,
            email:  email,
        });
        if (res.success) {
            $('#sb-forgot-msg').css('color','#28a745').text(res.data.message);
        } else {
            $('#sb-forgot-msg').css('color','#ff4d4d').text(res.data.message);
        }
        btn.prop('disabled', false).text('Reset-Link senden');
    });

    // ── Login ──
    $(document).on('click', '#sb-login-btn', async function(){
        const btn  = $(this).prop('disabled', true).text('Einloggen…');
        const user = $('#sb-login-user').val().trim();
        const pass = $('#sb-login-pass').val();
        const bid  = parseInt($('#sb-post-booking-id').val() || '0');
        if (!user || !pass) { $('#sb-login-error').text('Bitte alle Felder ausfüllen.'); btn.prop('disabled',false).text('Einloggen'); return; }

        const res = await $.post(studiobookAjax.ajaxurl, {
            action: 'studiobook_login', nonce: studiobookAjax.nonce,
            login: user, password: pass, booking_id: bid,
        });
        if (res.success) {
            window.location.href = res.data.redirect || window.location.href;
        } else {
            $('#sb-login-error').text(res.data.message);
            btn.prop('disabled',false).text('Einloggen');
        }
    });

    // Enter-Taste für Login
    $(document).on('keypress', '#sb-login-user, #sb-login-pass', function(e){
        if (e.which === 13) $('#sb-login-btn').click();
    });

    // ── Registrierung ──
    $(document).on('click', '#sb-reg-btn', async function(){
        const btn = $(this).prop('disabled', true).text('Erstelle Konto…');
        const username = $('#sb-reg-username').val().trim();
        const email    = $('#sb-reg-email').val().trim();
        const artist   = $('#sb-reg-artist').val().trim();
        const pass     = $('#sb-reg-pass').val();
        const pass2    = $('#sb-reg-pass2').val();
        const bid      = parseInt($('#sb-post-booking-id').val() || '0');

        if (!username || !email || !pass) { $('#sb-reg-error').text('Bitte alle Pflichtfelder ausfüllen.'); btn.prop('disabled',false).text('Konto erstellen'); return; }
        if (pass !== pass2) { $('#sb-reg-error').text('Passwörter stimmen nicht überein.'); btn.prop('disabled',false).text('Konto erstellen'); return; }

        const res = await $.post(studiobookAjax.ajaxurl, {
            action: 'studiobook_register', nonce: studiobookAjax.nonce,
            username, email, password: pass, artist_name: artist, booking_id: bid,
        });
        if (res.success) {
            window.location.href = res.data.redirect || window.location.href;
        } else {
            $('#sb-reg-error').text(res.data.message);
            btn.prop('disabled',false).text('Konto erstellen');
        }
    });

    // ── Post-Booking Auth Tabs ──
    $(document).on('click', '.sb-post-auth-tab', function(){
        const tab = $(this).data('tab');
        $('.sb-post-auth-tab').removeClass('active');
        $(this).addClass('active');
        $('.sb-post-auth-panel').hide();
        $('#sb-post-' + tab).show();
    });

    // ── Logout ──
    $(document).on('click', '#sb-logout-btn', async function(){
        const res = await $.post(studiobookAjax.ajaxurl, {
            action: 'studiobook_logout', nonce: studiobookAjax.nonce,
        });
        if (res.success) window.location.href = res.data.redirect || window.location.href;
    });

    // ── Mobile Sidebar ──
    $(document).on('click', '#sb-hamburger', function(){
        $('#sb-sidebar').addClass('open');
        $('#sb-overlay').addClass('active');
        $('body').css('overflow', 'hidden');
    });

    function closeSidebar(){
        $('#sb-sidebar').removeClass('open');
        $('#sb-overlay').removeClass('active');
        $('body').css('overflow', '');
    }

    $(document).on('click', '#sb-sidebar-close, #sb-overlay', closeSidebar);
    $(document).on('click', '.sb-nav-item:not(#sb-logout-btn)', function(){ closeSidebar(); });

    // ── Profil speichern ──
    $(document).on('click', '#sb-save-profile-btn', function(){
        const btn = $(this).prop('disabled', true).text('Speichern…');
        const formData = new FormData();
        formData.append('action', 'studiobook_update_profile');
        formData.append('nonce', studiobookAjax.nonce);
        formData.append('artist_name', $('#sb-set-artist').val());
        formData.append('first_name',  $('#sb-set-firstname').val());
        formData.append('last_name',   $('#sb-set-lastname').val());
        formData.append('email',       $('#sb-set-email').val());

        const avatarFile = document.getElementById('sb-avatar-file');
        if (avatarFile && avatarFile.files[0]) formData.append('avatar', avatarFile.files[0]);

        $.ajax({
            url: studiobookAjax.ajaxurl, type: 'POST',
            data: formData, processData: false, contentType: false,
            success: function(res){
                const msg = $('#sb-profile-msg');
                if (res.success) {
                    msg.removeClass('error').addClass('success').text(res.data.message);
                    // Avatar-Vorschau aktualisieren
                    if (avatarFile && avatarFile.files[0]) {
                        const reader = new FileReader();
                        reader.onload = function(e){
                            $('#sb-avatar-preview').attr('src', e.target.result).show();
                        };
                        reader.readAsDataURL(avatarFile.files[0]);
                    }
                    setTimeout(() => location.reload(), 1200);
                } else {
                    msg.removeClass('success').addClass('error').text(res.data.message);
                }
                btn.prop('disabled', false).text('Profil speichern');
            }
        });
    });

    // Avatar Vorschau
    $(document).on('change', '#sb-avatar-file', function(){
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(e){
            const preview = document.getElementById('sb-avatar-preview');
            if (preview.tagName === 'IMG') {
                preview.src = e.target.result;
            } else {
                // Placeholder div durch img ersetzen
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'sb-settings-avatar';
                img.id = 'sb-avatar-preview';
                preview.parentNode.replaceChild(img, preview);
            }
        };
        reader.readAsDataURL(file);
    });

    // ── Passwort ändern ──
    $(document).on('click', '#sb-save-pw-btn', async function(){
        const btn = $(this).prop('disabled', true).text('Ändere…');
        const current = $('#sb-pw-current').val();
        const newpw   = $('#sb-pw-new').val();
        const confirm = $('#sb-pw-confirm').val();
        const msg     = $('#sb-pw-msg');

        const res = await $.post(studiobookAjax.ajaxurl, {
            action: 'studiobook_change_password', nonce: studiobookAjax.nonce,
            current_password: current, new_password: newpw, confirm_password: confirm,
        });

        if (res.success) {
            msg.removeClass('error').addClass('success').text(res.data.message);
            $('#sb-pw-current, #sb-pw-new, #sb-pw-confirm').val('');
        } else {
            msg.removeClass('success').addClass('error').text(res.data.message);
        }
        btn.prop('disabled', false).text('Passwort ändern');
    });

}); // end doc ready
})(jQuery);

// ── Nav Dropdown (Login-Icon) ──
(function($){
$(function(){

    // Toggle dropdown
    $(document).on('click', '.sb-nav-icon-btn[aria-haspopup]', function(e){
        e.stopPropagation();
        var $drop = $(this).siblings('.sb-nav-dropdown');
        var isOpen = $drop.hasClass('open');
        $('.sb-nav-dropdown').removeClass('open');
        $('.sb-nav-icon-btn').attr('aria-expanded','false');
        if (!isOpen) {
            $drop.addClass('open');
            $(this).attr('aria-expanded','true');
        }
    });

    // Close on outside click
    $(document).on('click', function(){
        $('.sb-nav-dropdown').removeClass('open');
        $('.sb-nav-icon-btn').attr('aria-expanded','false');
    });

    // Logout from dropdown
    $(document).on('click', '.sb-nav-dropdown-logout', async function(){
        var nonce = $(this).data('nonce');
        var res = await $.post(studiobookAjax.ajaxurl, {
            action: 'studiobook_logout', nonce: nonce,
        });
        if (res.success) window.location.href = res.data.redirect || '/';
    });

});
})(jQuery);
