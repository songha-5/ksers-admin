<script>
(function () {
    var config = window.ksersAdminMail || { template: '', templates: {} };
    var storageKey = 'ksersAdminMailDraft';
    var subject = document.getElementById('mailSubject');
    var title = document.getElementById('mailTitle');
    var body = document.getElementById('mailBody');
    var desk = document.getElementById('deskInfo');
    var deskWrap = document.getElementById('deskWrap');
    var deskLabel = document.getElementById('deskLabel');
    var templatePick = document.getElementById('templatePick');
    var templateField = document.getElementById('templateField');
    var recipientField = document.getElementById('recipientField');
    var recipientList = document.getElementById('recipientList');
    var recipientCount = document.getElementById('recipientCount');
    var langField = document.getElementById('mailLangField');
    var current = (config.templates && config.templates[config.template]) || {};
    var locales = current.locales || null;
    var active = config.mailLang === 'en' ? 'en' : 'ko';
    var packs = null;
    var recipients = [];
    var applied = {
        subject: subject.value,
        title: title.value,
        body: body.value,
        desk: desk ? desk.value : ''
    };
    var appliedPacks = null;

    function cloneLocales(source) {
        var out = {};
        ['ko', 'en'].forEach(function (lang) {
            var item = source[lang] || {};
            out[lang] = {
                subject: item.subject || '',
                title: item.title || '',
                body: item.body || '',
                desk: item.desk || '',
                category: item.category || '',
                rows: item.rows || [],
                button_label: item.button_label || '',
                notice: item.notice || '',
                send_reason: item.send_reason || ''
            };
        });
        return out;
    }
    if (locales && locales.ko && locales.en) {
        packs = cloneLocales(locales);
        packs[active].subject = subject.value;
        packs[active].title = title.value;
        packs[active].body = body.value;
        packs[active].desk = desk ? desk.value : '';
    }

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[ch];
        });
    }
    function capture() {
        if (!packs || !packs[active]) {
            return;
        }
        packs[active].subject = subject.value;
        packs[active].title = title.value;
        packs[active].body = body.value;
        packs[active].desk = desk ? desk.value : '';
    }
    function snapshot(source) {
        var out = {};
        ['ko', 'en'].forEach(function (lang) {
            out[lang] = {
                subject: source[lang].subject,
                title: source[lang].title,
                body: source[lang].body,
                desk: source[lang].desk || ''
            };
        });
        return out;
    }
    function syncHidden() {
        if (!packs) {
            return;
        }
        ['ko', 'en'].forEach(function (lang) {
            var subjectInput = document.getElementById('subject_' + lang);
            var titleInput = document.getElementById('mail_title_' + lang);
            var bodyInput = document.getElementById('body_' + lang);
            var deskInput = document.getElementById('desk_' + lang);
            if (subjectInput) subjectInput.value = packs[lang].subject;
            if (titleInput) titleInput.value = packs[lang].title;
            if (bodyInput) bodyInput.value = packs[lang].body;
            if (deskInput) deskInput.value = packs[lang].desk || '';
        });
        if (langField) {
            langField.value = active;
        }
    }
    function hasDesk(rows) {
        return (rows || []).some(function (row) {
            return String(row[1] || '').indexOf('{{desk_info}}') !== -1;
        });
    }
    function paint(pack) {
        var rows = document.getElementById('mailRows');
        var html = '';
        (pack.rows || []).forEach(function (row) {
            var value = String(row[1] || '').replace(/\{\{desk_info\}\}/g, pack.desk || '');
            html += '<tr><th>' + esc(row[0]) + '</th><td>' + esc(value) + '</td></tr>';
        });
        if (rows) {
            rows.innerHTML = html;
        }
        var button = document.getElementById('mailButton');
        var buttonWrap = document.getElementById('mailButtonWrap');
        if (button) {
            button.textContent = pack.button_label || '';
        }
        if (buttonWrap) {
            buttonWrap.style.display = pack.button_label ? '' : 'none';
        }
        var notice = document.getElementById('mailNotice');
        var noticeWrap = document.getElementById('mailNoticeWrap');
        if (notice) {
            notice.textContent = pack.notice || '';
        }
        if (noticeWrap) {
            noticeWrap.style.display = pack.notice ? '' : 'none';
        }
        var category = document.getElementById('mailCategory');
        if (category) {
            category.textContent = pack.category || '';
        }
        var greeting = document.getElementById('mailGreeting');
        if (greeting) {
            greeting.textContent = active === 'ko' ? '받는 사람님, 안녕하세요.' : 'Dear Recipient,';
        }
        var signoff = document.getElementById('mailSignoff');
        if (signoff) {
            signoff.innerHTML = active === 'ko'
                ? '문의 사항은 사무국(ksers2027@ksers.kr)으로 연락 주시기 바랍니다.<br><br>감사합니다.<br><strong>KSERS 2027 사무국</strong>'
                : 'If you have any questions, please contact the Secretariat at ksers2027@ksers.kr.<br><br>Best regards,<br><strong>KSERS 2027 Secretariat</strong>';
        }
        var foot = document.getElementById('mailFoot');
        if (foot) {
            foot.innerHTML = active === 'ko'
                ? 'KSERS 2027 사무국<br>이 메일은 KSERS 2027 ' + esc(pack.send_reason || '') + '와 관련하여 발송되었습니다.'
                : 'KSERS 2027 Secretariat<br>This e-mail was sent in connection with your ' + esc(pack.send_reason || '') + ' for KSERS 2027.';
        }
        if (deskWrap) {
            deskWrap.classList.toggle('d-none', !hasDesk(pack.rows));
        }
        if (deskLabel) {
            deskLabel.textContent = active === 'ko' ? '현장 등록데스크' : 'On-site Registration Desk';
        }
        document.querySelectorAll('#mailLangSwitch [data-mail-lang]').forEach(function (button) {
            var on = button.getAttribute('data-mail-lang') === active;
            button.classList.toggle('btn-primary', on);
            button.classList.toggle('btn-outline-primary', !on);
        });
    }
    function show(lang) {
        if (!packs || !packs[lang] || lang === active) {
            return;
        }
        capture();
        active = lang;
        var pack = packs[lang];
        subject.value = pack.subject;
        title.value = pack.title;
        body.value = pack.body;
        if (desk) {
            desk.value = pack.desk || '';
        }
        paint(pack);
        syncHidden();
        saveDraft();
    }

    function draft() {
        capture();
        return {
            template: config.template,
            subject: subject.value,
            title: title.value,
            body: body.value,
            desk: desk ? desk.value : '',
            mailLang: active,
            packs: packs,
            recipients: recipients
        };
    }
    function saveDraft() {
        sessionStorage.setItem(storageKey, JSON.stringify(draft()));
    }
    function dirty() {
        capture();
        if (packs && appliedPacks) {
            return JSON.stringify(snapshot(packs)) !== JSON.stringify(snapshot(appliedPacks));
        }
        return subject.value !== applied.subject || title.value !== applied.title || body.value !== applied.body || (desk ? desk.value : '') !== applied.desk;
    }
    function renderRecipients() {
        recipientList.innerHTML = '';
        recipients.forEach(function (person, index) {
            var item = document.createElement('li');
            item.className = 'list-group-item';
            var text = document.createElement('span');
            text.textContent = (person.name || person.email) + ' · ' + person.email;
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-sm btn-outline-danger';
            button.textContent = '삭제';
            button.addEventListener('click', function () {
                recipients.splice(index, 1);
                renderRecipients();
                saveDraft();
            });
            item.appendChild(text);
            item.appendChild(button);
            recipientList.appendChild(item);
        });
        recipientCount.textContent = String(recipients.length);
        recipientField.value = JSON.stringify(recipients);
    }
    function addPerson(person) {
        var email = String(person.email || '').trim().toLowerCase();
        if (!email) {
            return;
        }
        var exists = recipients.some(function (row) {
            return String(row.email || '').trim().toLowerCase() === email;
        });
        if (exists) {
            return;
        }
        recipients.push(person);
    }
    var saved = sessionStorage.getItem(storageKey);
    if (saved) {
        try {
            var parsed = JSON.parse(saved);
            if (parsed && parsed.template === config.template) {
                if (packs && parsed.packs && parsed.packs.ko && parsed.packs.en) {
                    ['ko', 'en'].forEach(function (lang) {
                        packs[lang].subject = parsed.packs[lang].subject || packs[lang].subject;
                        packs[lang].title = parsed.packs[lang].title || packs[lang].title;
                        packs[lang].body = parsed.packs[lang].body || packs[lang].body;
                        packs[lang].desk = parsed.packs[lang].desk || '';
                    });
                    active = parsed.mailLang === 'en' ? 'en' : 'ko';
                    subject.value = packs[active].subject;
                    title.value = packs[active].title;
                    body.value = packs[active].body;
                    if (desk) {
                        desk.value = packs[active].desk || '';
                    }
                    paint(packs[active]);
                } else {
                    subject.value = parsed.subject || subject.value;
                    title.value = parsed.title || title.value;
                    body.value = parsed.body || body.value;
                    if (desk && typeof parsed.desk === 'string') {
                        desk.value = parsed.desk;
                    }
                }
                recipients = Array.isArray(parsed.recipients) ? parsed.recipients : [];
            }
        } catch (error) {
            recipients = [];
        }
    }
    if (packs) {
        appliedPacks = cloneLocales(packs);
        paint(packs[active]);
        syncHidden();
    }
    renderRecipients();
    [subject, title, body].forEach(function (field) {
        field.addEventListener('input', function () {
            capture();
            syncHidden();
            saveDraft();
        });
    });
    if (desk) {
        desk.addEventListener('input', function () {
            capture();
            if (packs && packs[active]) {
                paint(packs[active]);
            }
            syncHidden();
            saveDraft();
        });
    }
    document.querySelectorAll('#mailLangSwitch [data-mail-lang]').forEach(function (button) {
        button.addEventListener('click', function () {
            show(button.getAttribute('data-mail-lang'));
        });
    });
    document.getElementById('addRecipients').addEventListener('click', function () {
        var allAuthors = document.getElementById('allAuthors');
        var expand = allAuthors && allAuthors.checked;
        document.querySelectorAll('.candidate-check:checked').forEach(function (box) {
            var person;
            try {
                person = JSON.parse(box.getAttribute('data-payload'));
            } catch (error) {
                return;
            }
            if (expand) {
                addPerson(person);
                if (Array.isArray(person.authors)) {
                    person.authors.forEach(function (author) {
                        addPerson({
                            email: author.email,
                            name: author.name,
                            role: '저자',
                            context: person.context || {}
                        });
                    });
                }
            } else {
                addPerson(person);
            }
            box.checked = false;
        });
        renderRecipients();
        saveDraft();
    });
    var filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', saveDraft);
    }
    var pending = '';
    var templateModal = new bootstrap.Modal(document.getElementById('templateModal'));
    templatePick.addEventListener('change', function () {
        var next = templatePick.value;
        if (next === config.template) {
            return;
        }
        if (dirty()) {
            pending = next;
            templatePick.value = config.template;
            templateModal.show();
            return;
        }
        sessionStorage.removeItem(storageKey);
        window.location.href = templateUrl(next);
    });
    document.getElementById('templateConfirm').addEventListener('click', function () {
        sessionStorage.removeItem(storageKey);
        window.location.href = templateUrl(pending);
    });
    function templateUrl(next) {
        var url = 'index.php?template=' + encodeURIComponent(next) + '&filter=all';
        if (next === 'payment_wait') {
            url += '&unpaid=1';
        }
        if (next === 'attend') {
            url += '&attend=1';
        }
        return url;
    }
    var unpaidPick = document.getElementById('unpaidPick');
    var filterLocks = ['filterPick', 'classPick', 'typePick', 'acceptPick', 'regPick'];
    function syncUnpaid() {
        var locked = (unpaidPick && unpaidPick.checked) || config.template === 'attend';
        filterLocks.forEach(function (id) {
            var field = document.getElementById(id);
            if (field) {
                field.disabled = locked;
            }
        });
    }
    if (unpaidPick) {
        unpaidPick.addEventListener('change', syncUnpaid);
        syncUnpaid();
    }
    var sendModal = new bootstrap.Modal(document.getElementById('sendModal'));
    document.getElementById('sendMail').addEventListener('click', function () {
        if (!recipients.length) {
            document.getElementById('sendModalBody').textContent = '발송 목록에 받을 사람을 추가해 주세요.';
            document.getElementById('sendConfirm').hidden = true;
            sendModal.show();
            return;
        }
        document.getElementById('sendConfirm').hidden = false;
        document.getElementById('sendModalBody').textContent = String(config.confirmSend || '').replace('%d', String(recipients.length));
        sendModal.show();
    });
    document.getElementById('sendConfirm').addEventListener('click', function () {
        capture();
        syncHidden();
        templateField.value = config.template;
        recipientField.value = JSON.stringify(recipients);
        sessionStorage.removeItem(storageKey);
        document.getElementById('mailSendForm').submit();
    });
    var resultModal = document.getElementById('mailResultModal');
    if (resultModal) {
        new bootstrap.Modal(resultModal).show();
    }
})();
</script>
