<script>
(function () {
    var form = document.getElementById('newsForm');
    var area = document.getElementById('newsEditor');
    var title = document.getElementById('newsTitle');
    var titleHint = document.getElementById('newsTitleHint');
    var bodyHint = document.getElementById('newsBodyHint');
    var uploadHint = document.getElementById('newsUploadHint');
    if (!form || !area || typeof Jodit === 'undefined') {
        return;
    }
    var labels = form.dataset;

    function showUploadError(message) {
        if (!uploadHint) {
            return;
        }
        uploadHint.textContent = message;
        uploadHint.hidden = false;
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
        });
    }

    function uploadFile(kind, accept, place) {
        var input = document.createElement('input');
        input.type = 'file';
        input.accept = accept;
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) {
                return;
            }
            var data = new FormData();
            data.append('action', 'upload');
            data.append('kind', kind);
            data.append('file', file);
            fetch('action.php', { method: 'POST', body: data })
                .then(function (response) {
                    return response.json().then(function (json) {
                        return { ok: response.ok, json: json };
                    });
                })
                .then(function (result) {
                    if (!result.ok || !result.json.url) {
                        showUploadError(result.json.error || labels.uploadError || 'The file could not be uploaded.');
                        return;
                    }
                    if (uploadHint) {
                        uploadHint.hidden = true;
                    }
                    place(result.json);
                })
                .catch(function () {
                    showUploadError(labels.uploadError || 'The file could not be uploaded.');
                });
        });
        input.click();
    }

    var editor = Jodit.make(area, {
        language: 'ko',
        height: 420,
        toolbarAdaptive: false,
        showCharsCounter: false,
        showWordsCounter: false,
        showXPathInStatusbar: false,
        askBeforePasteHTML: false,
        askBeforePasteFromWord: false,
        defaultActionOnPaste: 'insert_clear_html',
        buttons: ['bold', 'italic', 'underline', 'paragraph', 'ul', 'ol', 'link', 'image', 'newsVideo', 'newsFile', 'table'],
        controls: {
            paragraph: {
                list: { p: '문단', h1: '제목 1', h2: '제목 2', h3: '제목 3', h4: '제목 4' }
            },
            newsVideo: {
                icon: 'video',
                tooltip: labels.video || 'Video',
                exec: function (instance) {
                    uploadFile('video', 'video/mp4,video/webm,.mp4,.webm', function (file) {
                        instance.s.insertHTML('<video src="' + escapeHtml(file.url) + '"></video>');
                    });
                }
            },
            newsFile: {
                icon: 'file',
                tooltip: labels.file || 'File',
                exec: function (instance) {
                    uploadFile('file', '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.hwp,.hwpx,.txt,.csv', function (file) {
                        var name = escapeHtml(file.name || 'file');
                        instance.s.insertHTML('<p><a href="' + escapeHtml(file.url) + '" download="' + name + '">' + name + '</a></p>');
                    });
                }
            }
        },
        uploader: {
            url: 'action.php',
            filesVariableName: function () { return 'file'; },
            imagesExtensions: ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            prepareData: function (formData) {
                formData.append('action', 'upload');
                formData.append('kind', 'image');
            },
            isSuccess: function (resp) { return !!(resp && resp.url); },
            getMessage: function (resp) { return (resp && resp.error) || ''; },
            process: function (resp) {
                return {
                    files: resp.url ? [resp.url] : [],
                    isImages: [true],
                    baseurl: '',
                    error: resp.error ? 1 : 0,
                    msg: resp.error || ''
                };
            }
        }
    });

    function bodyIsEmpty(html) {
        var holder = document.createElement('div');
        holder.innerHTML = html || '';
        if (holder.querySelector('img, video, table')) {
            return false;
        }
        var text = (holder.textContent || '').replace(/\u00a0/g, ' ').trim();
        return text === '';
    }

    if (title && titleHint) {
        title.addEventListener('input', function () {
            if (title.value.trim() !== '') {
                titleHint.hidden = true;
            }
        });
    }
    editor.events.on('change', function () {
        if (bodyHint && !bodyIsEmpty(editor.value)) {
            bodyHint.hidden = true;
        }
    });

    form.addEventListener('submit', function (event) {
        area.value = editor.value;
        var submitter = event.submitter;
        var action = submitter ? submitter.value : 'save';
        if (action === 'delete' && !window.confirm(labels.deleteConfirm || 'Delete this post?')) {
            event.preventDefault();
            return;
        }
        if (action !== 'save') {
            return;
        }
        var titleEmpty = !title || title.value.trim() === '';
        var emptyBody = bodyIsEmpty(editor.value);
        if (titleHint) {
            titleHint.hidden = !titleEmpty;
        }
        if (bodyHint) {
            bodyHint.hidden = !emptyBody;
        }
        if (titleEmpty || emptyBody) {
            event.preventDefault();
            if (titleEmpty && title) {
                title.focus();
            }
        }
    });
})();
</script>
