(function () {
    const config = window.ksersLeave || {};
    const korea = !!config.korea;
    const copy = {
        profile: korea
            ? '수정한 회원정보가 저장되지 않았습니다. 수정을 완료하려면 \'저장\' 버튼을 눌러 주세요. 저장하지 않고 이동하시겠습니까?'
            : 'Your changes have not been saved. To save them, please click "Save." Leave without saving?',
        registration: korea
            ? '등록 내용이 저장되지 않았습니다. \'등록 내용 저장\' 버튼을 눌러 주세요. 저장하지 않고 이동하시겠습니까?'
            : 'Your registration has not been saved. Please click "Save Registration." Leave without saving?',
        unpaid: korea
            ? '등록 내용은 저장되었지만 결제가 완료되지 않았습니다. 결제를 완료해야 등록이 확정됩니다. 마이페이지에서 언제든 결제를 이어서 할 수 있습니다.'
            : 'Your registration has been saved, but payment has not been completed. Your registration will be confirmed once payment is completed. You can continue with payment anytime on My Page.',
        abstract: korea
            ? '작성 중인 초록이 저장되지 않았습니다. 제출(수정)을 완료하려면 \'저장\' 버튼을 눌러 주세요. 저장하지 않고 이동하시겠습니까?'
            : 'Your abstract has not been saved. To submit or update it, please click "Save." Leave without saving?',
        materials: korea
            ? '작성 중인 초록·영상 정보가 저장되지 않았습니다. 제출(수정)을 완료하려면 \'저장\' 버튼을 눌러 주세요. 저장하지 않고 이동하시겠습니까?'
            : 'Your abstract and video have not been saved. To submit or update them, please click "Save." Leave without saving?'
    };
    const modal = document.getElementById('leaveModal');
    const message = document.getElementById('leaveModalMessage');
    const leaveButton = document.getElementById('leaveModalGo');
    const stayButton = document.getElementById('leaveModalStay');
    if (!modal || !message || !leaveButton || !stayButton) {
        return;
    }

    const snapshots = new Map();
    let allowLeave = false;
    let pendingHref = '';

    window.ksersAllowLeave = function () {
        allowLeave = true;
    };

    function fieldToken(field) {
        if (field.type === 'file') {
            return field.name + ':file:' + (field.files ? field.files.length : 0);
        }
        if (field.type === 'checkbox' || field.type === 'radio') {
            return field.name + ':' + field.value + ':' + (field.checked ? '1' : '0');
        }
        return field.name + ':' + field.value;
    }

    function formToken(form) {
        const parts = [];
        form.querySelectorAll('input, select, textarea').forEach(function (field) {
            if (field.type === 'button' || field.type === 'submit') {
                return;
            }
            parts.push(fieldToken(field));
        });
        return parts.join('\n');
    }

    function remember(form) {
        snapshots.set(form, formToken(form));
    }

    document.querySelectorAll('form[data-leave]').forEach(function (form) {
        remember(form);
        form.addEventListener('submit', function () {
            allowLeave = true;
        });
    });

    function dirtyKind() {
        let kind = '';
        document.querySelectorAll('form[data-leave]').forEach(function (form) {
            if (!snapshots.has(form)) {
                remember(form);
            }
            if (snapshots.get(form) !== formToken(form)) {
                kind = form.getAttribute('data-leave') || kind;
            }
        });
        return kind;
    }

    function warningText() {
        const kind = dirtyKind();
        if (kind && copy[kind]) {
            return copy[kind];
        }
        if (config.unpaid) {
            return copy.unpaid;
        }
        return '';
    }

    function openModal(href) {
        pendingHref = href;
        message.textContent = warningText();
        modal.classList.add('is-open');
        modal.removeAttribute('hidden');
        stayButton.focus();
    }

    function closeModal() {
        pendingHref = '';
        modal.classList.remove('is-open');
        modal.setAttribute('hidden', '');
    }

    stayButton.addEventListener('click', closeModal);
    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });
    leaveButton.addEventListener('click', function () {
        if (pendingHref === '') {
            closeModal();
            return;
        }
        allowLeave = true;
        window.location.href = pendingHref;
    });

    document.addEventListener('click', function (event) {
        if (allowLeave) {
            return;
        }
        const link = event.target.closest('a[href]');
        if (!link || link.closest('#leaveModal')) {
            return;
        }
        if (link.target === '_blank' || link.hasAttribute('download')) {
            return;
        }
        const inlineClick = link.getAttribute('onclick') || '';
        if (inlineClick.indexOf('ksersOpenSheet') !== -1 || inlineClick.indexOf('ksersOpenConfirmation') !== -1) {
            return;
        }
        const raw = link.getAttribute('href') || '';
        if (raw === '' || raw.charAt(0) === '#' || raw.indexOf('javascript:') === 0 || raw.indexOf('mailto:') === 0 || raw.indexOf('tel:') === 0) {
            return;
        }
        let url;
        try {
            url = new URL(link.href, window.location.href);
        } catch (error) {
            return;
        }
        if (url.origin === window.location.origin && url.pathname === window.location.pathname && url.search === window.location.search) {
            return;
        }
        if (warningText() === '') {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        openModal(link.href);
    }, true);

    window.addEventListener('beforeunload', function (event) {
        if (allowLeave || warningText() === '') {
            return;
        }
        event.preventDefault();
        event.returnValue = '';
    });
})();
