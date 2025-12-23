// exams.js - JavaScript específico para gestão de exames

class ExamsManager {
    constructor() {
        this.initEventListeners();
        this.initDataTable();
        this.initFilters();
        this.initDateRangePicker();
    }

    initEventListeners() {
        // Botão de novo exame
        document.getElementById('newExamBtn')?.addEventListener('click', () => {
            window.location.href = 'exam_edit.php?action=create';
        });

        // Botão de exportar
        document.getElementById('exportBtn')?.addEventListener('click', () => {
            this.exportData();
        });

        // Botão de sincronização
        document.getElementById('syncBtn')?.addEventListener('click', () => {
            this.manualSync();
        });

        // Formulário de filtro
        document.getElementById('filterForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.applyFilters();
        });

        // Botão de limpar filtros
        document.getElementById('clearFiltersBtn')?.addEventListener('click', () => {
            this.clearFilters();
        });

        // Upload de PDF
        this.initPDFUpload();

        // Visualização rápida de laudo
        this.initQuickView();
    }

    initDataTable() {
        // Inicializar DataTable se disponível
        if (typeof $.fn.DataTable !== 'undefined') {
            $('#examsTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.25/i18n/Portuguese-Brasil.json'
                },
                pageLength: 25,
                order: [[0, 'desc']], // Ordenar por data mais recente
                responsive: true,
                columnDefs: [
                    {
                        targets: [0], // Coluna de data
                        type: 'date-eu' // Formato de data europeu (DD/MM/YYYY)
                    },
                    {
                        targets: '_all', // Todas as colunas
                        className: 'text-center'
                    }
                ]
            });
        }
    }

    initFilters() {
        // Filtro por status
        const statusFilter = document.getElementById('statusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', () => {
                this.applyFilters();
            });
        }

        // Filtro por data rápida
        const quickDateFilter = document.getElementById('quickDateFilter');
        if (quickDateFilter) {
            quickDateFilter.addEventListener('change', () => {
                this.applyQuickDateFilter(quickDateFilter.value);
            });
        }
    }

    initDateRangePicker() {
        // Inicializar date range picker se disponível
        if (typeof $.fn.daterangepicker !== 'undefined') {
            $('#dateRangePicker').daterangepicker({
                locale: {
                    format: 'DD/MM/YYYY',
                    separator: ' - ',
                    applyLabel: 'Aplicar',
                    cancelLabel: 'Cancelar',
                    fromLabel: 'De',
                    toLabel: 'Até',
                    customRangeLabel: 'Personalizado',
                    daysOfWeek: ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'],
                    monthNames: [
                        'Janeiro', 'Fevereiro', 'Março', 'Abril', 
                        'Maio', 'Junho', 'Julho', 'Agosto',
                        'Setembro', 'Outubro', 'Novembro', 'Dezembro'
                    ],
                    firstDay: 0
                },
                ranges: {
                    'Hoje': [moment(), moment()],
                    'Ontem': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Últimos 7 dias': [moment().subtract(6, 'days'), moment()],
                    'Últimos 30 dias': [moment().subtract(29, 'days'), moment()],
                    'Este mês': [moment().startOf('month'), moment().endOf('month')],
                    'Mês passado': [
                        moment().subtract(1, 'month').startOf('month'),
                        moment().subtract(1, 'month').endOf('month')
                    ]
                },
                startDate: moment().subtract(30, 'days'),
                endDate: moment()
            }, (start, end, label) => {
                this.applyDateRange(start.format('YYYY-MM-DD'), end.format('YYYY-MM-DD'));
            });
        }
    }

    initPDFUpload() {
        const uploadForm = document.getElementById('uploadPDFForm');
        if (uploadForm) {
            uploadForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.uploadPDF(uploadForm);
            });
        }

        // Drag and drop
        const dropZone = document.getElementById('dropZone');
        if (dropZone) {
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });

            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('dragover');
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    this.handleDroppedFiles(files[0]);
                }
            });
        }
    }

    initQuickView() {
        // Adicionar eventos para visualização rápida
        document.addEventListener('click', (e) => {
            if (e.target.closest('.quick-view-btn')) {
                const examId = e.target.closest('.quick-view-btn').dataset.examId;
                this.showQuickView(examId);
            }
        });
    }

    async showQuickView(examId) {
        try {
            const response = await fetch(`../api/exams.php?id=${examId}`);
            const exam = await response.json();
            
            this.displayQuickViewModal(exam);
        } catch (error) {
            console.error('Erro ao carregar exame:', error);
            alert('Erro ao carregar informações do exame');
        }
    }

    displayQuickViewModal(exam) {
        const modalContent = `
            <div class="modal-header">
                <h5 class="modal-title">Exame ${exam.exam_number}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Paciente</h6>
                        <p><strong>${this.escapeHtml(exam.patient_name)}</strong></p>
                        <p>Data: ${this.formatDate(exam.exam_date)} ${exam.exam_time}</p>
                        
                        ${exam.heart_rate ? `
                            <p>FC: <span class="badge bg-danger">${exam.heart_rate} bpm</span></p>
                        ` : ''}
                        
                        ${exam.blood_pressure ? `
                            <p>PA: <span class="badge bg-info">${exam.blood_pressure}</span></p>
                        ` : ''}
                    </div>
                    <div class="col-md-6">
                        <h6>Status</h6>
                        <p>
                            <span class="badge bg-${exam.pdf_processed ? 'success' : 'warning'}">
                                ${exam.pdf_processed ? 'Com Laudo' : 'Pendente'}
                            </span>
                        </p>
                        
                        ${exam.resp_doctor ? `
                            <p>Médico: ${this.escapeHtml(exam.resp_doctor)}</p>
                        ` : ''}
                        
                        ${exam.observations ? `
                            <h6 class="mt-3">Observações</h6>
                            <p><small>${this.escapeHtml(exam.observations)}</small></p>
                        ` : ''}
                    </div>
                </div>
                
                ${exam.stored_filename ? `
                    <div class="mt-3">
                        <a href="../api/pdf_viewer.php?exam_id=${exam.id}" 
                           target="_blank" class="btn btn-sm btn-danger">
                            <i class="bi bi-file-pdf"></i> Visualizar Laudo
                        </a>
                    </div>
                ` : ''}
            </div>
            <div class="modal-footer">
                <a href="exam_detail.php?id=${exam.id}" class="btn btn-primary">
                    <i class="bi bi-eye"></i> Ver Detalhes
                </a>
                <a href="exam_edit.php?id=${exam.id}" class="btn btn-secondary">
                    <i class="bi bi-pencil"></i> Editar
                </a>
            </div>
        `;
        
        const modal = document.getElementById('quickViewModal');
        if (modal) {
            modal.querySelector('.modal-content').innerHTML = modalContent;
            new bootstrap.Modal(modal).show();
        }
    }

    async manualSync() {
        const syncBtn = document.getElementById('syncBtn');
        const originalText = syncBtn.innerHTML;
        
        syncBtn.disabled = true;
        syncBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sincronizando...';
        
        try {
            const response = await fetch('../api/sync.php?action=manual');
            const data = await response.json();
            
            if (data.success) {
                alert('Sincronização realizada com sucesso!');
                setTimeout(() => location.reload(), 1000);
            } else {
                alert('Erro na sincronização: ' + (data.message || 'Erro desconhecido'));
                syncBtn.innerHTML = originalText;
                syncBtn.disabled = false;
            }
        } catch (error) {
            alert('Erro na sincronização: ' + error.message);
            syncBtn.innerHTML = originalText;
            syncBtn.disabled = false;
        }
    }

    applyFilters() {
        const form = document.getElementById('filterForm');
        const formData = new FormData(form);
        
        const params = new URLSearchParams();
        for (const [key, value] of formData.entries()) {
            if (value) {
                params.set(key, value);
            }
        }
        
        window.location.href = `exams.php?${params.toString()}`;
    }

    applyQuickDateFilter(range) {
        const today = new Date();
        let startDate, endDate;
        
        switch (range) {
            case 'today':
                startDate = endDate = this.formatDateForInput(today);
                break;
            case 'yesterday':
                const yesterday = new Date(today);
                yesterday.setDate(yesterday.getDate() - 1);
                startDate = endDate = this.formatDateForInput(yesterday);
                break;
            case 'week':
                const weekAgo = new Date(today);
                weekAgo.setDate(weekAgo.getDate() - 7);
                startDate = this.formatDateForInput(weekAgo);
                endDate = this.formatDateForInput(today);
                break;
            case 'month':
                const monthAgo = new Date(today);
                monthAgo.setMonth(monthAgo.getMonth() - 1);
                startDate = this.formatDateForInput(monthAgo);
                endDate = this.formatDateForInput(today);
                break;
            default:
                return;
        }
        
        window.location.href = `exams.php?start_date=${startDate}&end_date=${endDate}`;
    }

    applyDateRange(startDate, endDate) {
        window.location.href = `exams.php?start_date=${startDate}&end_date=${endDate}`;
    }

    clearFilters() {
        window.location.href = 'exams.php';
    }

    exportData() {
        const format = prompt('Escolha o formato de exportação:\n1. CSV\n2. Excel\n3. PDF', '1');
        
        let exportUrl;
        switch (format) {
            case '1':
                exportUrl = '../api/export.php?type=exams&format=csv';
                break;
            case '2':
                exportUrl = '../api/export.php?type=exams&format=excel';
                break;
            case '3':
                exportUrl = '../api/export.php?type=exams&format=pdf';
                break;
            default:
                return;
        }
        
        // Adicionar filtros atuais à URL de exportação
        const currentParams = new URLSearchParams(window.location.search);
        currentParams.forEach((value, key) => {
            if (key !== 'page') {
                exportUrl += `&${key}=${encodeURIComponent(value)}`;
            }
        });
        
        window.open(exportUrl, '_blank');
    }

    uploadPDF(form) {
        const formData = new FormData(form);
        const examId = formData.get('exam_id');
        const fileInput = form.querySelector('input[type="file"]');
        
        if (!fileInput.files.length) {
            alert('Por favor, selecione um arquivo PDF');
            return;
        }
        
        // Validar tipo de arquivo
        const file = fileInput.files[0];
        if (!file.type.includes('pdf') && !file.name.toLowerCase().endsWith('.pdf')) {
            alert('Por favor, selecione um arquivo PDF válido');
            return;
        }
        
        // Validar tamanho (10MB máximo)
        if (file.size > 10 * 1024 * 1024) {
            alert('O arquivo é muito grande. Tamanho máximo: 10MB');
            return;
        }
        
        // Mostrar progresso
        const progressBar = form.querySelector('.progress-bar');
        if (progressBar) {
            progressBar.style.width = '0%';
            progressBar.textContent = '0%';
        }
        
        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                if (progressBar) {
                    progressBar.style.width = percentComplete + '%';
                    progressBar.textContent = Math.round(percentComplete) + '%';
                }
            }
        });
        
        xhr.addEventListener('load', () => {
            try {
                const response = JSON.parse(xhr.responseText);
                
                if (response.success) {
                    alert('Laudo enviado com sucesso!');
                    location.reload();
                } else {
                    alert('Erro: ' + response.message);
                    if (progressBar) {
                        progressBar.style.width = '0%';
                        progressBar.textContent = 'Falhou';
                    }
                }
            } catch (error) {
                alert('Erro ao processar resposta do servidor');
                console.error(error);
            }
        });
        
        xhr.addEventListener('error', () => {
            alert('Erro de conexão. Verifique sua internet e tente novamente.');
            if (progressBar) {
                progressBar.style.width = '0%';
                progressBar.textContent = 'Erro';
            }
        });
        
        xhr.open('POST', '../api/sync.php?action=upload_pdf');
        xhr.send(formData);
    }

    handleDroppedFiles(file) {
        // Validar se é PDF
        if (!file.type.includes('pdf') && !file.name.toLowerCase().endsWith('.pdf')) {
            alert('Por favor, solte apenas arquivos PDF');
            return;
        }
        
        // Preencher formulário de upload
        const fileInput = document.querySelector('input[type="file"]');
        if (fileInput) {
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            fileInput.files = dataTransfer.files;
            
            // Disparar evento de change
            fileInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    deleteExam(examId, examNumber) {
        if (confirm(`Tem certeza que deseja excluir o exame "${examNumber}"?\n\nEsta ação não pode ser desfeita.`)) {
            fetch(`../api/exams.php?id=${examId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Exame excluído com sucesso!');
                    location.reload();
                } else {
                    alert(`Erro: ${data.error || 'Não foi possível excluir o exame'}`);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao excluir exame. Verifique sua conexão.');
            });
        }
    }

    openUploadModal(examId) {
        document.getElementById('uploadExamId').value = examId;
        const modal = new bootstrap.Modal(document.getElementById('uploadModal'));
        modal.show();
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('pt-BR');
    }

    formatDateForInput(date) {
        return date.toISOString().split('T')[0];
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Estatísticas em tempo real
    async updateExamStats() {
        try {
            const response = await fetch('../api/stats.php?period=today');
            const stats = await response.json();
            
            this.updateStatsDisplay(stats);
        } catch (error) {
            console.error('Erro ao atualizar estatísticas:', error);
        }
    }

    updateStatsDisplay(stats) {
        // Atualizar contadores na página
        const elements = {
            'totalExams': stats.total_exams,
            'examsToday': stats.exams_today,
            'pendingExams': stats.pending_reports,
            'coverageRate': stats.coverage_rate ? stats.coverage_rate + '%' : '0%'
        };
        
        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
        
        // Atualizar gráfico se existir
        this.updateChart(stats);
    }

    updateChart(stats) {
        // Implementar atualização de gráfico
        // (Depende da biblioteca de gráficos usada)
    }
}

// Inicializar quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', () => {
    window.examsManager = new ExamsManager();
    
    // Atualizar estatísticas a cada 60 segundos
    setInterval(() => {
        window.examsManager.updateExamStats();
    }, 60000);
});

// Funções globais para uso em eventos inline
function confirmDeleteExam(examId, examNumber) {
    window.examsManager.deleteExam(examId, examNumber);
}

function uploadReport(examId) {
    window.examsManager.openUploadModal(examId);
}

// Máscaras para formulários de exame
function initExamFormMasks() {
    // Número do exame (somente números e letras)
    const examNumberInputs = document.querySelectorAll('input[name="exam_number"]');
    examNumberInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
    });

    // Frequência cardíaca (somente números, 30-300)
    const hrInputs = document.querySelectorAll('input[name="heart_rate"]');
    hrInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = Math.min(Math.max(parseInt(value) || 0, 30), 300);
            e.target.value = value || '';
        });
    });

    // Pressão arterial (formato 120/80)
    const bpInputs = document.querySelectorAll('input[name="blood_pressure"]');
    bpInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d/]/g, '');
            
            // Limitar a um caractere '/'
            const parts = value.split('/');
            if (parts.length > 2) {
                value = parts[0] + '/' + parts[1];
            }
            
            e.target.value = value.substring(0, 7); // Ex: 999/999
        });
    });

    // Peso (formato decimal)
    const weightInputs = document.querySelectorAll('input[name="weight"]');
    weightInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d.]/g, '');
            
            // Permitir apenas um ponto decimal
            const parts = value.split('.');
            if (parts.length > 2) {
                value = parts[0] + '.' + parts[1];
            }
            
            // Limitar casas decimais
            if (parts.length === 2 && parts[1].length > 1) {
                value = parts[0] + '.' + parts[1].substring(0, 1);
            }
            
            e.target.value = value;
        });
    });

    // Altura (formato decimal)
    const heightInputs = document.querySelectorAll('input[name="height"]');
    heightInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d.]/g, '');
            
            // Permitir apenas um ponto decimal
            const parts = value.split('.');
            if (parts.length > 2) {
                value = parts[0] + '.' + parts[1];
            }
            
            // Limitar casas decimais
            if (parts.length === 2 && parts[1].length > 2) {
                value = parts[0] + '.' + parts[1].substring(0, 2);
            }
            
            e.target.value = value;
        });
    });
}

// Validação de formulário de exame
function validateExamForm() {
    const form = document.getElementById('examForm');
    if (!form) return true;

    const requiredFields = ['exam_number', 'patient_id', 'exam_date', 'exam_time'];
    let isValid = true;
    let errorMessage = '';

    requiredFields.forEach(fieldName => {
        const field = form.querySelector(`[name="${fieldName}"]`);
        if (field && !field.value.trim()) {
            isValid = false;
            field.classList.add('is-invalid');
            
            const fieldLabel = field.previousElementSibling?.textContent || fieldName;
            errorMessage += `• ${fieldLabel} é obrigatório\n`;
        } else if (field) {
            field.classList.remove('is-invalid');
        }
    });

    // Validar frequência cardíaca
    const hrField = form.querySelector('[name="heart_rate"]');
    if (hrField && hrField.value.trim()) {
        const hr = parseInt(hrField.value);
        if (isNaN(hr) || hr < 30 || hr > 300) {
            isValid = false;
            hrField.classList.add('is-invalid');
            errorMessage += '• Frequência cardíaca deve estar entre 30 e 300 bpm\n';
        }
    }

    // Validar pressão arterial
    const bpField = form.querySelector('[name="blood_pressure"]');
    if (bpField && bpField.value.trim()) {
        const bpRegex = /^\d{2,3}\/\d{2,3}$/;
        if (!bpRegex.test(bpField.value)) {
            isValid = false;
            bpField.classList.add('is-invalid');
            errorMessage += '• Pressão arterial deve estar no formato 120/80\n';
        }
    }

    if (!isValid) {
        alert('Por favor, corrija os seguintes erros:\n\n' + errorMessage);
    }

    return isValid;
}

// Auto-complete para busca de pacientes no formulário de exame
function initPatientAutoComplete() {
    const patientSearch = document.getElementById('patientSearch');
    const patientIdField = document.getElementById('patient_id');
    
    if (!patientSearch || !patientIdField) return;
    
    let searchTimeout;
    
    patientSearch.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        const searchTerm = e.target.value.trim();
        
        if (searchTerm.length >= 2) {
            searchTimeout = setTimeout(() => {
                searchPatients(searchTerm);
            }, 300);
        } else {
            clearSuggestions();
        }
    });
    
    async function searchPatients(searchTerm) {
        try {
            const response = await fetch(`../api/patients.php?action=search&term=${encodeURIComponent(searchTerm)}&limit=5`);
            const patients = await response.json();
            
            showPatientSuggestions(patients);
        } catch (error) {
            console.error('Erro ao buscar pacientes:', error);
        }
    }
    
    function showPatientSuggestions(patients) {
        clearSuggestions();
        
        if (patients.length === 0) return;
        
        const suggestionsContainer = document.createElement('div');
        suggestionsContainer.className = 'autocomplete-suggestions';
        suggestionsContainer.style.position = 'absolute';
        suggestionsContainer.style.zIndex = '1000';
        suggestionsContainer.style.backgroundColor = 'white';
        suggestionsContainer.style.border = '1px solid #ddd';
        suggestionsContainer.style.maxHeight = '200px';
        suggestionsContainer.style.overflowY = 'auto';
        suggestionsContainer.style.width = patientSearch.offsetWidth + 'px';
        
        patients.forEach(patient => {
            const suggestion = document.createElement('div');
            suggestion.className = 'autocomplete-suggestion';
            suggestion.innerHTML = `
                <strong>${escapeHtml(patient.full_name)}</strong>
                <small class="text-muted d-block">
                    CPF: ${patient.cpf || 'Não informado'} | 
                    Nasc: ${patient.birth_date ? formatDate(patient.birth_date) : 'Não informado'}
                </small>
            `;
            
            suggestion.addEventListener('click', () => {
                patientSearch.value = patient.full_name;
                patientIdField.value = patient.id;
                clearSuggestions();
            });
            
            suggestionsContainer.appendChild(suggestion);
        });
        
        patientSearch.parentNode.appendChild(suggestionsContainer);
    }
    
    function clearSuggestions() {
        const existing = document.querySelector('.autocomplete-suggestions');
        if (existing) {
            existing.remove();
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('pt-BR');
    }
}

// Inicializar quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', () => {
    // Inicializar máscaras
    initExamFormMasks();
    
    // Inicializar auto-complete
    initPatientAutoComplete();
    
    // Adicionar validação ao formulário
    const examForm = document.getElementById('examForm');
    if (examForm) {
        examForm.addEventListener('submit', function(e) {
            if (!validateExamForm()) {
                e.preventDefault();
            }
        });
    }
});