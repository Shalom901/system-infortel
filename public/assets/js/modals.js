// Global Dynamic Interactions Setup

// 1. Modal Logic
function openModal(modalId) {
    // Dynamically inject a modal if it doesn't exist
    if (!document.getElementById(modalId)) {
        const modalHtml = `
            <div id="${modalId}" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900 bg-opacity-50 transition-opacity">
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all scale-100">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-4" id="${modalId}-title">Alerta</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-400 mb-6" id="${modalId}-content">¿Está seguro de realizar esta acción?</p>
                    <div class="flex justify-end space-x-3">
                        <button onclick="closeModal('${modalId}')" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200 rounded">Cancelar</button>
                        <button id="${modalId}-confirm" class="px-4 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded">Confirmar</button>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    } else {
        document.getElementById(modalId).classList.remove('hidden');
    }
}

function closeModal(modalId) {
    const el = document.getElementById(modalId);
    if (el) el.classList.add('hidden');
}

// 2. Delete Confirmation Modal
function confirmDelete(id) {
    const modalId = 'deleteModal';
    openModal(modalId);
    document.getElementById(`${modalId}-title`).innerText = 'Confirmar Eliminación';
    document.getElementById(`${modalId}-content`).innerText = `¿Está seguro de que desea eliminar el registro ${id}? Esta acción no se puede deshacer.`;
    
    const confirmBtn = document.getElementById(`${modalId}-confirm`);
    confirmBtn.className = "px-4 py-2 bg-rose-500 hover:bg-rose-600 text-white rounded";
    confirmBtn.onclick = function() {
        // Here we would run AJAX delete
        confirmBtn.innerHTML = 'Eliminando...';
        setTimeout(() => {
            closeModal(modalId);
            showToast('Registro eliminado exitosamente', 'success');
        }, 800);
    };
}

// 3. AJAX Form Submission
function submitFormAjax(event, url) {
    event.preventDefault();
    const form = event.target;
    const btnText = form.querySelector('#btnText');
    const spinner = form.querySelector('#btnSpinner');
    
    if (btnText) btnText.innerText = 'Procesando...';
    if (spinner) spinner.classList.remove('hidden');

    // Simulate AJAX Request
    setTimeout(() => {
        if (btnText) btnText.innerText = 'Guardado';
        if (spinner) spinner.classList.add('hidden');
        showToast('Configuración guardada correctamente.', 'success');
        
        setTimeout(() => {
            if (btnText) btnText.innerText = 'Guardar Configuración';
        }, 2000);
    }, 1200);
}

// 4. Toast Notifications
function showToast(message, type = 'success') {
    const bgColor = type === 'success' ? 'text-green-500 bg-green-100 dark:bg-green-800 dark:text-green-200' : 'text-red-500 bg-red-100 dark:bg-red-800 dark:text-red-200';
    const toastHtml = `
        <div class="fixed bottom-5 right-5 z-50 transform transition-all duration-300 translate-y-0 opacity-100 flex items-center p-4 mb-4 w-full max-w-xs text-gray-500 bg-white rounded-lg shadow dark:text-gray-400 dark:bg-slate-800" role="alert">
            <div class="inline-flex flex-shrink-0 justify-center items-center w-8 h-8 rounded-lg ${bgColor}">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
            </div>
            <div class="ml-3 text-sm font-normal">${message}</div>
        </div>
    `;
    
    const div = document.createElement('div');
    div.innerHTML = toastHtml.trim();
    document.body.appendChild(div.firstChild);
    
    const toastEl = document.body.lastChild;
    setTimeout(() => {
        toastEl.classList.add('translate-y-2', 'opacity-0');
        setTimeout(() => toastEl.remove(), 300);
    }, 3000);
}
