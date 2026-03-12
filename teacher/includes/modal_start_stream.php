<?php
/**
 * Stream (Axın/Patok) Lesson Start Modal
 * Müəllimə eyni fənni tədris edən çoxlu ixtisasları seçərək vahid canlı dərs başlatmaq imkanı verir.
 */
?>
<!-- Stream Lesson Modal -->
<div id="streamLiveModal" class="modal"
    style="display: none; align-items: center; justify-content: center; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 9999; backdrop-filter: blur(4px);">
    <div class="modal-content"
        style="background: white; width: 90%; max-width: 600px; border-radius: 28px; border: none; overflow: hidden; box-shadow: 0 40px 100px -20px rgba(0,0,0,0.3); animation: modalSpring 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);">
        <div class="modal-header"
            style="padding: 24px 30px; background: linear-gradient(135deg, #7c3aed, #6d28d9); border-bottom: none; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <i data-lucide="radio" style="width: 22px; height: 22px; color: white;"></i>
                </div>
                <div>
                    <h2 style="font-size: 20px; font-weight: 700; color: white; margin: 0;">Axın Dərsi Yarat</h2>
                    <p style="font-size: 12px; color: rgba(255,255,255,0.7); margin: 0;">Çoxlu ixtisas — Bir dərs</p>
                </div>
            </div>
            <button class="modal-close" onclick="closeStreamModal()"
                style="background: rgba(255,255,255,0.2); border: none; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: white;">
                <i data-lucide="x" style="width: 20px; height: 20px;"></i>
            </button>
        </div>

        <div class="modal-body" style="padding: 30px; background: white;">
            <input type="hidden" id="stream_course_level">

            <!-- Step 1: Fənn seçimi -->
            <div class="form-group" style="margin-bottom: 24px;">
                <label style="font-size: 13px; font-weight: 600; color: #718096; margin-bottom: 8px; display: block;">
                    Fənn seçin
                </label>
                <select id="stream_subject_select" class="form-input" onchange="onStreamSubjectChange()"
                    style="border: 1px solid #E2E8F0; border-radius: 12px; padding: 12px 16px; width: 100%; font-size: 14px; color: #2D3748;">
                    <option value="">-- Fənn seçin --</option>
                </select>
            </div>

            <!-- Step 2: İxtisasları seçin (checkbox list) -->
            <div id="stream_majors_section" style="display: none; margin-bottom: 24px;">
                <label style="font-size: 13px; font-weight: 600; color: #718096; margin-bottom: 12px; display: block;">
                    İxtisasları seçin
                    <span style="font-weight: 400; color: #a0aec0;">(ən azı 2)</span>
                </label>
                <div style="display: flex; gap: 8px; margin-bottom: 12px;">
                    <button type="button" onclick="selectAllMajors()" 
                        style="background: #f0f9ff; color: #0E5995; border: 1px solid #bfdbfe; padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer;">
                        Hamısını seç
                    </button>
                    <button type="button" onclick="deselectAllMajors()"
                        style="background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer;">
                        Sıfırla
                    </button>
                </div>
                <div id="stream_majors_list"
                    style="max-height: 200px; overflow-y: auto; border: 1px solid #E2E8F0; border-radius: 12px; padding: 8px;">
                    <!-- Dinamik olaraq doldurulacaq -->
                </div>
                <div id="stream_selected_count" style="font-size: 12px; color: #718096; margin-top: 8px;">
                    Seçilmiş: 0
                </div>
            </div>

            <!-- Step 3: Dərs növü -->
            <div style="margin-bottom: 24px;">
                <label style="font-size: 13px; font-weight: 600; color: #718096; margin-bottom: 12px; display: block;">
                    Dərs Növü
                </label>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                    <div id="stream_card_lecture" class="lesson-type-card stream-type-card" onclick="selectStreamLessonType('lecture')"
                        style="border: 2px solid #EDF2F7; background: #F7FAFC; cursor: pointer; padding: 15px 10px; border-radius: 20px; text-align: center; transition: all 0.2s;">
                        <i data-lucide="book-open" style="width: 24px; height: 24px; margin: 0 auto 10px; display: block; color: #A0AEC0;"></i>
                        <span style="display: block; font-weight: 700; color: #718096; font-size: 14px;">Mühazirə</span>
                        <input type="radio" name="stream_lesson_type" value="lecture" style="display: none;">
                    </div>
                    <div id="stream_card_seminar" class="lesson-type-card stream-type-card" onclick="selectStreamLessonType('seminar')"
                        style="border: 2px solid #EDF2F7; background: #F7FAFC; cursor: pointer; padding: 15px 10px; border-radius: 20px; text-align: center; transition: all 0.2s;">
                        <i data-lucide="users" style="width: 24px; height: 24px; margin: 0 auto 10px; display: block; color: #A0AEC0;"></i>
                        <span style="display: block; font-weight: 700; color: #718096; font-size: 14px;">Seminar</span>
                        <input type="radio" name="stream_lesson_type" value="seminar" style="display: none;">
                    </div>
                    <div id="stream_card_laboratory" class="lesson-type-card stream-type-card" onclick="selectStreamLessonType('laboratory')"
                        style="border: 2px solid #EDF2F7; background: #F7FAFC; cursor: pointer; padding: 15px 10px; border-radius: 20px; text-align: center; transition: all 0.2s;">
                        <i data-lucide="beaker" style="width: 24px; height: 24px; margin: 0 auto 10px; display: block; color: #A0AEC0;"></i>
                        <span style="display: block; font-weight: 700; color: #718096; font-size: 14px;">Laboratoriya</span>
                        <input type="radio" name="stream_lesson_type" value="laboratory" style="display: none;">
                    </div>
                </div>
            </div>

            <!-- Step 4: Mövzu adı -->
            <div class="form-group" style="margin-bottom: 0;">
                <label for="stream_topic_name"
                    style="font-size: 13px; font-weight: 600; color: #718096; margin-bottom: 8px; display: block;">
                    Mövzu Adı
                </label>
                <input type="text" id="stream_topic_name" class="form-input" placeholder="Məs: Ümumi Sənət Tarixi"
                    style="border: 1px solid #E2E8F0; border-radius: 12px; padding: 14px 16px; width: 100%; outline: none; font-size: 14px; color: #2D3748;"
                    required>
            </div>
        </div>

        <div class="modal-footer"
            style="padding: 24px 30px; background: white; border-top: 1px solid #f1f5f9; display: flex; gap: 16px;">
            <button type="button" onclick="closeStreamModal()"
                style="flex: 1; background: #F7FAFC; border: none; color: #4A5568; font-weight: 700; border-radius: 14px; padding: 14px; cursor: pointer; transition: background 0.2s;">
                Ləğv et
            </button>
            <button type="button" id="streamStartBtn" onclick="startStreamClass()"
                style="flex: 2; background: linear-gradient(135deg, #7c3aed, #6d28d9); border: none; color: white; font-weight: 700; border-radius: 14px; padding: 14px; cursor: pointer; box-shadow: 0 10px 20px rgba(124, 58, 237, 0.2); transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <i data-lucide="radio" style="width: 18px; height: 18px;"></i>
                Axın Dərsini Başlat
            </button>
        </div>
    </div>
</div>

<style>
    .stream-type-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
    }
    .stream-type-card.active {
        background: #f5f3ff !important;
        border-color: #7c3aed !important;
    }
    .stream-type-card.active span {
        color: #7c3aed !important;
    }
    .stream-type-card.active i,
    .stream-type-card.active svg {
        color: #7c3aed !important;
    }
    .stream-major-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        border-radius: 10px;
        cursor: pointer;
        transition: background 0.15s;
    }
    .stream-major-item:hover {
        background: #f7fafc;
    }
    .stream-major-item input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: #7c3aed;
        cursor: pointer;
    }
    .stream-major-item label {
        cursor: pointer;
        font-size: 14px;
        color: #2D3748;
        font-weight: 500;
        flex: 1;
    }
    .stream-major-item .major-detail {
        font-size: 11px;
        color: #a0aec0;
        font-weight: 400;
    }
    #stream_topic_name:focus {
        border-color: #7c3aed !important;
        box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.08);
    }
</style>

<script>
    // Kurslar datası PHP-dən JavaScript-ə ötürülür
    var allStreamCourses = <?php echo json_encode($courses ?? []); ?>;

    function openStreamModal() {
        // Fənn siyahısını unique subject_name ilə doldur
        const select = document.getElementById('stream_subject_select');
        select.innerHTML = '<option value="">-- Fənn seçin --</option>';

        // Unikal fənn adlarını topla
        const subjectMap = {};
        allStreamCourses.forEach(c => {
            const sName = (c.title || '').trim();
            if (!sName) return;
            if (!subjectMap[sName]) subjectMap[sName] = [];
            subjectMap[sName].push(c);
        });

        // Yalnız 2+ ixtisası olan fənləri göstər
        Object.keys(subjectMap).sort().forEach(sName => {
            if (subjectMap[sName].length >= 2) {
                const opt = document.createElement('option');
                opt.value = sName;
                opt.textContent = sName + ' (' + subjectMap[sName].length + ' ixtisas)';
                select.appendChild(opt);
            }
        });

        // Reset
        document.getElementById('stream_majors_section').style.display = 'none';
        document.getElementById('stream_majors_list').innerHTML = '';
        document.getElementById('stream_selected_count').textContent = 'Seçilmiş: 0';
        document.getElementById('stream_topic_name').value = '';
        selectStreamLessonType('lecture');

        const modal = document.getElementById('streamLiveModal');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeStreamModal() {
        document.getElementById('streamLiveModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    function onStreamSubjectChange() {
        const selectedSubject = document.getElementById('stream_subject_select').value;
        const listEl = document.getElementById('stream_majors_list');
        const sectionEl = document.getElementById('stream_majors_section');

        if (!selectedSubject) {
            sectionEl.style.display = 'none';
            listEl.innerHTML = '';
            return;
        }

        // Find all courses with this subject_name
        const matching = allStreamCourses.filter(c => (c.title || '').trim() === selectedSubject);

        listEl.innerHTML = '';
        matching.forEach((c, idx) => {
            const div = document.createElement('div');
            div.className = 'stream-major-item';
            div.innerHTML = `
                <input type="checkbox" id="stream_major_${c.id}" value="${c.id}" 
                       data-faculty="${c.description || ''}" data-level="${c.course_level || ''}"
                       onchange="updateStreamCount()" checked>
                <label for="stream_major_${c.id}">
                    ${c.specialization || 'İxtisas ' + (idx + 1)}
                    <span class="major-detail">${c.description ? ' — ' + c.description : ''} ${c.course_level ? '(Kurs: ' + c.course_level + ')' : ''}</span>
                </label>
            `;
            listEl.appendChild(div);
        });

        sectionEl.style.display = 'block';
        updateStreamCount();

        // Auto-fill topic
        if (!document.getElementById('stream_topic_name').value) {
            document.getElementById('stream_topic_name').value = 'Axın dərsi mövzusu';
        }

        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function updateStreamCount() {
        const checked = document.querySelectorAll('#stream_majors_list input[type="checkbox"]:checked');
        document.getElementById('stream_selected_count').textContent = 'Seçilmiş: ' + checked.length;
    }

    function selectAllMajors() {
        document.querySelectorAll('#stream_majors_list input[type="checkbox"]').forEach(cb => cb.checked = true);
        updateStreamCount();
    }

    function deselectAllMajors() {
        document.querySelectorAll('#stream_majors_list input[type="checkbox"]').forEach(cb => cb.checked = false);
        updateStreamCount();
    }

    function selectStreamLessonType(type) {
        const radios = document.getElementsByName('stream_lesson_type');
        radios.forEach(r => {
            if (r.value === type) r.checked = true;
        });

        const cards = {
            'lecture': document.getElementById('stream_card_lecture'),
            'seminar': document.getElementById('stream_card_seminar'),
            'laboratory': document.getElementById('stream_card_laboratory')
        };

        Object.keys(cards).forEach(key => {
            if (cards[key]) {
                cards[key].classList.toggle('active', key === type);
            }
        });
    }

    async function startStreamClass() {
        const selectedSubject = document.getElementById('stream_subject_select').value;
        const topicName = document.getElementById('stream_topic_name').value.trim();
        const lessonTypeRadio = document.querySelector('input[name="stream_lesson_type"]:checked');
        const lessonType = lessonTypeRadio ? lessonTypeRadio.value : 'lecture';

        if (!selectedSubject) {
            alert('Lütfən fənn seçin');
            return;
        }

        // Seçilmiş kurs ID-lərini topla
        const checkedBoxes = document.querySelectorAll('#stream_majors_list input[type="checkbox"]:checked');
        if (checkedBoxes.length < 2) {
            alert('Axın dərsi üçün ən azı 2 ixtisas seçilməlidir');
            return;
        }

        if (!topicName) {
            alert('Lütfən mövzu adını daxil edin');
            return;
        }

        const submitBtn = document.getElementById('streamStartBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i data-lucide="loader" class="animate-spin"></i> Başladılır...';
        if (typeof lucide !== 'undefined') lucide.createIcons();

        try {
            const formData = new FormData();
            checkedBoxes.forEach(cb => {
                formData.append('course_ids[]', cb.value);
            });
            formData.append('title', topicName);
            formData.append('lesson_type', lessonType);
            formData.append('course_name', selectedSubject);

            // İlk seçilmişdən metadata
            const firstCb = checkedBoxes[0];
            formData.append('faculty_name', firstCb.dataset.faculty || '');
            formData.append('course_level', firstCb.dataset.level || '1');

            const response = await fetch('api/start_stream_class.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.success) {
                window.location.href = `live-studio?id=${result.live_class_id}&subject_id=${result.subject_id}`;
            } else {
                alert('Xəta: ' + result.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        } catch (error) {
            alert('Xəta baş verdi: ' + error.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    }

    // ESC ilə bağla
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('streamLiveModal').style.display === 'flex') {
            closeStreamModal();
        }
    });

    // Modal xarici kliklə bağla
    document.addEventListener('DOMContentLoaded', function() {
        const streamModal = document.getElementById('streamLiveModal');
        if (streamModal) {
            streamModal.addEventListener('click', function(e) {
                if (e.target === streamModal) {
                    closeStreamModal();
                }
            });
        }
    });
</script>
