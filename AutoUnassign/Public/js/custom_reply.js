// == Toast Notification Function ==
function showToastAlert(message = "Berhasil!", type = "success") {
    // Cek kontainer utama toast
    let toastContainer = document.querySelector("#autoUnassignToastContainer");
    if (!toastContainer) {
        toastContainer = document.createElement("div");
        toastContainer.id = "autoUnassignToastContainer";
        toastContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        `;
        document.body.appendChild(toastContainer);
    }

    // Warna berdasarkan tipe
    let bgColor = "#e0f7e9", textColor = "#2e7d32", border = "#81c784";
    if (type === "error") {
        bgColor = "#fdecea"; textColor = "#c62828"; border = "#f44336";
    }

    const toast = document.createElement("div");
    toast.innerHTML = `✅ ${message}`;
    toast.style.cssText = `
        background: ${bgColor};
        color: ${textColor};
        border-left: 6px solid ${border};
        padding: 12px 18px;
        border-radius: 6px;
        font-size: 14px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        opacity: 0;
        transition: opacity 0.4s ease, transform 0.4s ease;
        transform: translateY(-10px);
    `;

    toastContainer.appendChild(toast);

    // Animasi masuk
    requestAnimationFrame(() => {
        toast.style.opacity = "1";
        toast.style.transform = "translateY(0)";
    });

    // Hapus setelah 2 detik
    setTimeout(() => {
        toast.style.opacity = "0";
        toast.style.transform = "translateY(-10px)";
        setTimeout(() => toast.remove(), 1000);
    }, 5000);
}

// == AutoUnassign Logic ==
document.addEventListener("DOMContentLoaded", function () {
    document.body.addEventListener('click', function (e) {
        if (e.target.closest('.conv-reply')) {
            const subjectEl = document.querySelector('#conv-subj-value');
            let subjectText = subjectEl ? subjectEl.value : '';

            console.log('[AutoUnassign] Subject text:', JSON.stringify(subjectText));
            if (!/Ticket\s*#?\s*\d+/i.test(subjectText)) {
                console.log('[AutoUnassign] Skip - subject tidak cocok');
                return;
            }

            let interval = setInterval(function () {
                const ccSelect = document.querySelector('select[name="cc[]"]');
                const toSelect = document.querySelector('select[name="to"]');
                if (!ccSelect || !toSelect) return;

                let ccCleared = false;
                let toForced = false;

                // === CLEAR & DISABLE CC ===
                if (!ccSelect.disabled && $(ccSelect).data('select2')) {
                    $(ccSelect).val([]).trigger('change');
                    $(ccSelect).prop('disabled', true);
                    //$(ccSelect).select2();
                    ccCleared = true;
                    console.log('[AutoUnassign] CC cleared & disabled');
                }

                // === FORCE & READONLY TO ===
                if (!toSelect.hasAttribute('data-forced')) {
                    const newEmail = "support@wowrack.com";
                    let found = false;
                    for (let i = 0; i < toSelect.options.length; i++) {
                        if (toSelect.options[i].value === newEmail) {
                            toSelect.value = newEmail;
                            found = true;
                            break;
                        }
                    }

                    if (!found) {
                        const newOption = document.createElement('option');
                        newOption.value = newEmail;
                        newOption.text = `Wowrack Support <${newEmail}>`;
                        newOption.selected = true;
                        toSelect.appendChild(newOption);
                    }

                    //$(toSelect).val(newEmail).trigger('change');
                    toSelect.value = newEmail;

                    // Simulasikan readonly (tanpa disabled)
                    toSelect.style.pointerEvents = 'none';
                    toSelect.style.backgroundColor = '#eee';
                    toSelect.style.color = '#666';
                    toSelect.setAttribute('data-forced', 'true'); // penanda agar tidak diulang

                    toForced = true;
                    console.log('[AutoUnassign] TO forced & readonly visual:', newEmail);
                }


                // === TOAST ALERT ===
                let msg = [];
                if (ccCleared) msg.push('Cc dibersihkan');
                if (toForced) msg.push('Email tujuan dikunci');
                if (msg.length > 0) {
                    showToastAlert(msg.join(' & '));
                }

                clearInterval(interval);
            }, 300);
        }
    });
});
