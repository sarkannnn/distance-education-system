<?php
/**
 * Stream (Patok) Lesson Modal
 * Allows teacher to select multiple majors with the same subject 
 * and start ONE shared live session for all of them.
 */
?>
<!-- Stream Lesson Modal -->
<div id="streamLessonModal" class="modal"
    style="display: none; align-items: center; justify-content: center; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 9999; backdrop-filter: blur(4px);">
    <div class="modal-content"
        style="background: white; width: 90%; max-width: 620px; border-radius: 28px; border: none; overflow: hidden; box-shadow: 0 40px 100px -20px rgba(0,0,0,0.3); animation: modalSpring 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);">

        <!-- Header -->
        <div
            style="padding: 24px 30px; background: linear-gradient(135deg, #7c3aed, #6d28d9); border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div
                    style="background: rgba(255,255,255,0.2); width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <i data-lucide="radio" style="width: 22px; height: 22px; color: white;"></i>
                </div>
                <div>
                    <h2 style="font-size: 20px; font-weight: 700; color: white; margin: 0;">Patok Dərs Yarat</h2>
                    <p style="font-size: 12px; color: rgba(255,255,255,0.7); margin: 0;">Bir neçə ixtisas üçün ortaq
                        dərs</p>
                </div>
            </div>
            <button onclick="closeStreamModal()"
                style="background: rgba(255,255,255,0.2); border: none; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: white;">
                <i data-lucide="x" style="width: 20px; height: 20px;"></i>
            </button>
        </div>
        <!-- Body -->
        <div style="padding: 30px; background: white; max-height: 60vh; overflow-y: auto;">

            <!-- Step 1: Subject Selection -->
            <div class="form-group" style="margin-bottom: 24px;">
                <label style="font-size: 13px; font-weight: 600; color: #718096; margin-bottom: 8px; display: block;">
                    <span
                        style="background: #7c3aed; color: white; width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; margin-right: 8px;">1</span>
                    Fənn Seçin
                </label>
                <select id="stream_subject_select" onchange="onStreamSubjectChange()"
                    style="width: 100%; padding: 12px 16px; border: 2px solid #E2E8F0; border-radius: 12px; font-size: 14px; color: #2D3748; background: #F7FAFC; cursor: pointer; outline: none; transition: border-color 0.2s;">
                    <option value="">— Fənn seçin —</option>
                </select>
            </div>
            <!-- Step 2: Major Checkboxes -->
            <div id="stream_majors_section" style="display: none; margin-bottom: 24px;">
                <label style="font-size: 13px; font-weight: 600; color: #718096; margin-bottom: 12px; display: block;">
                    <span
                        style="background: #7c3aed; color: white; width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; margin-right: 8px;">2</span>
                    İxtisasları Seçin
                    <span style="font-weight: 400; color: #a0aec0; font-size: 12px;">(bir neçəsini seçə
                        bilərsiniz)</span>
                </label>
                <div id="stream_majors_list" style="display: flex; flex-direction: column; gap: 10px;">
                    <!-- Dynamically populated -->
                </div>
                <div style="margin-top: 10px; display: flex; gap: 8px;">
                    <button type="button" onclick="streamSelectAll()"
                        style="background: #EDE9FE; color: #7c3aed; border: 1px solid #DDD6FE; padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer;">
                        Hamısını Seç
                    </button>
                    <button type="button" onclick="streamDeselectAll()"
                        style="background: #FEF2F2; color: #EF4444; border: 1px solid #FECACA; padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer;">
                        Hamısını Ləğv Et
                    </button>
                </div>
            </div>
            <!-- Step 3: Lesson Type -->
            <div id="stream_lesson_type_section" style="display: none; margin-bottom: 24px;">
                <label style="font-size: 13px; font-weight: 600; color: #718096; margin-bottom: 12px; display: block;">
                    <span
                        style="background: #7c3aed; color: white; width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; margin-right: 8px;">3</span>
                    Dərs Növü
                </label>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                    <div id="stream_card_lecture" class="stream-type-card active"
                        onclick="selectStreamLessonType('lecture')"
                        style="border: 2px solid #7c3aed; background: #f5f3ff; cursor: pointer; padding: 15px 10px; border-radius: 20px; text-align: center; transition: all 0.2s;">
                        <i data-lucide="book-open"
                            style="width: 24px; height: 24px; margin: 0 auto 10px; display: block; color: #7c3aed;"></i>
                        <span style="display: block; font-weight: 700; color: #7c3aed; font-size: 14px;">Mühazirə</span>
                        <input type="radio" name="stream_lesson_type" value="lecture" checked style="display: none;">
                    </div>
                    <div id="stream_card_seminar" class="stream-type-card" onclick="selectStreamLessonType('seminar')"
                        style="border: 2px solid #EDF2F7; background: #F7FAFC; cursor: pointer; padding: 15px 10px; border-radius: 20px; text-align: center; transition: all 0.2s;">
                        <i data-lucide="users"
                            style="width: 24px; height: 24px; margin: 0 auto 10px; display: block; color: #A0AEC0;"></i>
                        <span style="display: block; font-weight: 700; color: #718096; font-size: 14px;">Seminar</span>
                        <input type="radio" name="stream_lesson_type" value="seminar" style="display: none;">
                    </div>
                    <div id="stream_card_laboratory" class="stream-type-card"
                        onclick="selectStreamLessonType('laboratory')"
                        style="border: 2px solid #EDF2F7; background: #F7FAFC; cursor: pointer; padding: 15px 10px; border-radius: 20px; text-align: center; transition: all 0.2s;">
                        <i data-lucide="beaker"
                            style="width: 24px; height: 24px; margin: 0 auto 10px; display: block; color: #A0AEC0;"></i>
                        <span
                            style="display: block; font-weight: 700; color: #718096; font-size: 14px;">Laboratoriya</span>
                        <input type="radio" name="stream_lesson_type" value="laboratory" style="display: none;">
                    </div>
                </div>
            </div>
            <!-- Step 4: Topic -->
            <div id="stream_topic_section" style="display: none; margin-bottom: 0;">
                <label style="font-size: 13px; font-weight: 600; color: #718096; margin-bottom: 8px; display: block;">
                    <span
                        style="background: #7c3aed; color: white; width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; margin-right: 8px;">4</span>
                    Mövzu Adı
                </label>
                <input type="text" id="stream_topic_name" placeholder="Məs: Rəsm tarixinə giriş"
                    style="border: 2px solid #E2E8F0; border-radius: 12px; padding: 14px 16px; width: 100%; outline: none; font-size: 14px; color: #2D3748; transition: border-color 0.2s;"
                    onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#E2E8F0'">
            </div>
        </div>
        <!-- Footer -->
        <div style="padding: 24px 30px; background: white; border-top: 1px solid #f1f5f9; display: flex; gap: 16px;">
            <button type="button" onclick="closeStreamModal()"
                style="flex: 1; background: #F7FAFC; border: none; color: #4A5568; font-weight: 700; border-radius: 14px; padding: 14px; cursor: pointer; transition: background 0.2s;">
                Ləğv et
            </button>
            <button type="button" id="streamStartBtn" onclick="startStreamLesson()"
                style="flex: 2; background: linear-gradient(135deg, #7c3aed, #6d28d9); border: none; color: white; font-weight: 700; border-radius: 14px; padding: 14px; cursor: pointer; box-shadow: 0 10px 20px rgba(124, 58, 237, 0.3); transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <i data-lucide="radio" style="width: 18px; height: 18px;"></i>
                Patok Dərsi Başlat
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
        padding: 12px 16px;
        background: #F7FAFC;
        border: 2px solid #EDF2F7;
        border-radius: 14px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .stream-major-item:hover {
        border-color: #DDD6FE;
        background: #FAF5FF;
    }

    .stream-major-item.selected {
        border-color: #7c3aed;
        background: #f5f3ff;
    }

    .stream-major-item input[type="checkbox"] {
        width: 20px;
        height: 20px;
        accent-color: #7c3aed;
        cursor: pointer;
    }

    .stream-major-info {
        flex: 1;
    }

    .stream-major-name {
        font-weight: 600;
        color: #2D3748;
        font-size: 14px;
    }

    .stream-major-detail {
        font-size: 12px;
        color: #A0AEC0;
        margin-top: 2px;
    }

    #stream_subject_select:focus {
        border-color: #7c3aed;
        box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
    }
</style>
<script>
    // Courses data grouped by subject name (injected from PHP in courses.php)
    // window.streamCoursesGrouped is set in courses.php
    function openStreamModal() {
        const modal = document.getElementById('streamLessonModal');
        const select = document.getElementById('stream_subject_select');

        // Populate subject dropdown
        select.innerHTML = '<option value="">— Fənn seçin —</option>';
        const grouped = window.streamCoursesGrouped || {};

        Object.keys(grouped).forEach(subjectName => {
            const courses = grouped[subjectName];
            // Only show subjects that appear in more than 1 major
            if (courses.length > 1) {
                const opt = document.createElement('option');
                opt.value = subjectName;
                opt.textContent = subjectName + ' (' + courses.length + ' ixtisas)';
                select.appendChild(opt);
            }
        });
        // If no multi-major subjects exist, show a message
        if (select.options.length <= 1) {
            select.innerHTML = '<option value="">— Eyni fənnli bir neçə ixtisas tapılmadı —</option>';
        }
        // Reset sections
        document.getElementById('stream_majors_section').style.display = 'none';
        document.getElementById('stream_lesson_type_section').style.display = 'none';
        document.getElementById('stream_topic_section').style.display = 'none';
        document.getElementById('stream_topic_name').value = '';

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
    function closeStreamModal() {
        document.getElementById('streamLessonModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    function onStreamSubjectChange() {
        const select = document.getElementById('stream_subject_select');
        const subjectName = select.value;
        const majorsSection = document.getElementById('stream_majors_section');
        const majorsList = document.getElementById('stream_majors_list');
        const lessonTypeSection = document.getElementById('stream_lesson_type_section');
        const topicSection = document.getElementById('stream_topic_section');
        if (!subjectName) {
            majorsSection.style.display = 'none';
            lessonTypeSection.style.display = 'none';
            topicSection.style.display = 'none';
            return;
        }
        const grouped = window.streamCoursesGrouped || {};
        const courses = grouped[subjectName] || [];
        // Build major checkboxes
        majorsList.innerHTML = '';
        courses.forEach((course, idx) => {
            const div = document.createElement('div');
            div.className = 'stream-major-item';
            div.onclick = function (e) {
                if (e.target.tagName !== 'INPUT') {
                    const cb = this.querySelector('input[type="checkbox"]');
                    cb.checked = !cb.checked;
                }
                this.classList.toggle('selected', this.querySelector('input').checked);
            };
            div.innerHTML = `
            <input type="checkbox" value="${course.id}" data-faculty="${course.faculty || ''}" data-specialty="${course.specialization || ''}" data-level="${course.course_level || 1}" checked>
            <div class="stream-major-info">
                <div class="stream-major-name">${course.specialization || 'İxtisas'}</div>
                <div class="stream-major-detail">${course.faculty || ''} · Kurs ${course.course_level || '?'} · ${course.students || 0} tələbə</div>
            </div>
        `;
            div.classList.add('selected');
            majorsList.appendChild(div);
        });
        majorsSection.style.display = 'block';
        lessonTypeSection.style.display = 'block';
        topicSection.style.display = 'block';
        // Set default topic
        document.getElementById('stream_topic_name').value = 'Patok dərs mövzusu';
        // Reset lesson type to lecture
        selectStreamLessonType('lecture');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
    function selectStreamLessonType(type) {
        const types = ['lecture', 'seminar', 'laboratory'];
        types.forEach(t => {
            const card = document.getElementById('stream_card_' + t);
            const radio = card.querySelector('input[type="radio"]');
            if (t === type) {
                card.classList.add('active');
                radio.checked = true;
            } else {
                card.classList.remove('active');
                radio.checked = false;
            }
        });
    }
    function streamSelectAll() {
        document.querySelectorAll('#stream_majors_list input[type="checkbox"]').forEach(cb => {
            cb.checked = true;
            cb.closest('.stream-major-item').classList.add('selected');
        });
    }
    function streamDeselectAll() {
        document.querySelectorAll('#stream_majors_list input[type="checkbox"]').forEach(cb => {
            cb.checked = false;
            cb.closest('.stream-major-item').classList.remove('selected');
        });
    }
    async function startStreamLesson() {
        const subjectName = document.getElementById('stream_subject_select').value;
        if (!subjectName) {
            alert('Lütfən fənn seçin');
            return;
        }
        const checkedBoxes = document.querySelectorAll('#stream_majors_list input[type="checkbox"]:checked');
        if (checkedBoxes.length < 2) {
            alert('Patok dərs üçün ən azı 2 ixtisas seçilməlidir');
            return;
        }
        const topicName = document.getElementById('stream_topic_name').value.trim();
        if (!topicName) {
            alert('Lütfən dərsin mövzusunu daxil edin');
            return;
        }
        const lessonType = document.querySelector('input[name="stream_lesson_type"]:checked').value;
        const btn = document.getElementById('streamStartBtn');
        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader" class="animate-spin"></i> Başladılır...';
        if (typeof lucide !== 'undefined') lucide.createIcons();
        try {
            const formData = new FormData();
            formData.append('subject_name', subjectName);
            formData.append('title', topicName);
            formData.append('lesson_type', lessonType);
            checkedBoxes.forEach(cb => {
                formData.append('course_ids[]', cb.value);
            });
            // Send first course's metadata
            const firstCb = checkedBoxes[0];
            formData.append('faculty_name', firstCb.dataset.faculty || '');
            formData.append('course_level', firstCb.dataset.level || '1');
            // Build combined specialty names
            const specialtyNames = [];
            checkedBoxes.forEach(cb => {
                if (cb.dataset.specialty) specialtyNames.push(cb.dataset.specialty);
            });
            formData.append('specialty_name', specialtyNames.join(', '));
            const response = await fetch('api/start_stream_class.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                window.location.href = `live-studio?id=${result.live_class_id}&subject_id=${result.subject_id}`;
            } else {
                alert('Xəta: ' + result.message);
                btn.disabled = false;
                btn.innerHTML = originalHTML;
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        } catch (error) {
            alert('Xəta baş verdi: ' + error.message);
            btn.disabled = false;
            btn.innerHTML = originalHTML;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    }
    // Close on outside click
    document.addEventListener('click', function (e) {
        const modal = document.getElementById('streamLessonModal');
        if (e.target === modal) closeStreamModal();
    });
    // Close with Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('streamLessonModal');
            if (modal && modal.style.display === 'flex') closeStreamModal();
        }
    });
</script>