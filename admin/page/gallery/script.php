<script>
function ksersGalleryEdit(url) {
    var popup = window.open(url, 'ksersGalleryEdit', 'width=760,height=880,resizable=yes,scrollbars=yes');
    if (!popup) {
        window.location.href = url;
    }
}

(function () {
    var text = <?= json_encode([
        'choose' => t('이미지를 선택해 주세요.', 'Choose an image.'),
        'one' => t('정말 삭제할까요?', 'Delete this image?'),
        'bulk' => t('선택한 이미지 %s개를 정말 삭제할까요?', 'Delete the %s selected images?'),
        'none' => t('체크박스를 최소 한 개 이상 선택해 주세요.', 'Select at least one checkbox.'),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var addForm = document.getElementById('galleryAddForm');
    var addInput = document.getElementById('galleryAddImage');
    var addHint = document.getElementById('galleryAddHint');
    var addPreview = document.getElementById('galleryAddPreview');
    var addPreviewImg = document.getElementById('galleryAddPreviewImg');
    var deleteModalEl = document.getElementById('galleryDeleteModal');
    var deleteModal = new bootstrap.Modal(deleteModalEl);
    var deleteBody = document.getElementById('galleryDeleteBody');
    var deleteConfirm = document.getElementById('galleryDeleteConfirm');
    var deleteForm = document.getElementById('galleryDeleteForm');
    var deleteSeqs = document.getElementById('galleryDeleteSeqs');
    var checkAll = document.getElementById('galleryCheckAll');

    function visibleChecks() {
        return Array.prototype.slice.call(document.querySelectorAll('.gallery-table tbody .gallery-check'));
    }

    function selectedChecks() {
        return visibleChecks().filter(function (box) { return box.checked; });
    }

    function syncCheckAll() {
        if (!checkAll) {
            return;
        }
        var boxes = visibleChecks();
        checkAll.checked = boxes.length > 0 && boxes.every(function (box) { return box.checked; });
    }

    function showPreview(file) {
        if (!file) {
            addPreview.hidden = true;
            addPreviewImg.removeAttribute('src');
            return;
        }
        var reader = new FileReader();
        reader.onload = function () {
            addPreviewImg.src = reader.result;
            addPreviewImg.alt = file.name;
            addPreview.hidden = false;
        };
        reader.readAsDataURL(file);
    }

    if (addInput) {
        addInput.addEventListener('change', function () {
            addHint.hidden = true;
            showPreview(addInput.files && addInput.files[0] ? addInput.files[0] : null);
        });
    }

    if (addForm) {
        addForm.addEventListener('submit', function (event) {
            if (!addInput.files || !addInput.files.length) {
                event.preventDefault();
                addHint.textContent = text.choose;
                addHint.hidden = false;
            }
        });
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            visibleChecks().forEach(function (box) {
                box.checked = checkAll.checked;
            });
        });
    }
    visibleChecks().forEach(function (box) {
        box.addEventListener('change', syncCheckAll);
    });

    function openDelete(mode, seq) {
        deleteConfirm.dataset.mode = mode;
        deleteConfirm.dataset.seq = seq || '';
        if (mode === 'none') {
            deleteBody.textContent = text.none;
        } else if (mode === 'one') {
            deleteBody.textContent = text.one;
        } else {
            deleteBody.textContent = text.bulk.replace('%s', String(selectedChecks().length));
        }
        deleteModal.show();
    }

    document.getElementById('galleryBulkDelete').addEventListener('click', function () {
        openDelete(selectedChecks().length ? 'bulk' : 'none');
    });
    document.querySelectorAll('.gallery-delete-one').forEach(function (button) {
        button.addEventListener('click', function () {
            openDelete('one', button.getAttribute('data-seq'));
        });
    });
    deleteConfirm.addEventListener('click', function () {
        if (deleteConfirm.dataset.mode === 'none') {
            deleteModal.hide();
            return;
        }
        deleteSeqs.replaceChildren();
        var seqs = deleteConfirm.dataset.mode === 'one'
            ? [deleteConfirm.dataset.seq]
            : selectedChecks().map(function (box) { return box.value; });
        if (!seqs.length || (seqs.length === 1 && !seqs[0])) {
            deleteBody.textContent = text.none;
            deleteConfirm.dataset.mode = 'none';
            return;
        }
        seqs.forEach(function (seq) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'seqs[]';
            input.value = seq;
            deleteSeqs.appendChild(input);
        });
        deleteForm.submit();
    });
})();
</script>
