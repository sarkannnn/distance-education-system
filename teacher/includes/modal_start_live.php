<?php
/**
 * Unified Live Class Start Modal
 */
?>
<!-- Start Live Class Modal -->
<div id="startLiveModal" class="modal"
    style="display: none; align-items: center; justify-content: center; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 9999; backdrop-filter: blur(4px);">
    <div class="modal-content"
        style="background: white; width: 90%; max-width: 520px; border-radius: 28px; border: none; overflow: hidden; box-shadow: 0 40px 100px -20px rgba(0,0,0,0.3); animation: modalSpring 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);">
        <div class="modal-header"
            style="padding: 24px 30px; background: white; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="font-size: 20px; font-weight: 700; color: #0E5995; margin: 0;">Canlı Dərsi Başlat</h2>
            <button class="modal-close" onclick="closeStartLiveModal()"
                style="background: #f8fafc; border: none; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #64748b;">
                <i data-lucide="x" style="width: 20px; height: 20px;"></i>
            </button>
        </div>

        <div class="modal-body" style="padding: 30px; background: white;">
            <input type="hidden" id="live_course_id">
            <input type="hidden" id="live_course_level">
            <input type="hidden" id="live_faculty_name">
            <input type="hidden" id="live_specialty_name">

            <div class="form-group" style="margin-bottom: 24px;">
                <label
                    style="font-size: 13px; font-weight: 600; color: #718096; margin-bottom: 8px; display: block;">Fənn</label>
                <input type="text" id="live_course_name" class="form-input" disabled
                    style="background: #ffffff; border: 1px solid #E2E8F0; font-weight: 500; color: #2D3748; border-radius: 12px; padding: 12px 16px; width: 100%;">
            </div>

            <!-- Dərs Növü Seçimi -->
            <div style="margin-bottom: 24px;">
                <label
                    style="font-size: 13px; font-weight: 600; color: #718096; margin-bottom: 12px; display: block;">Dərs
                    Növü</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                    <div id="card_lecture" class="lesson-type-card" onclick="selectLessonType('lecture')"
                        style="border: 2px solid #EDF2F7; background: #F7FAFC; cursor: pointer; padding: 15px 10px; border-radius: 20px; text-align: center; transition: all 0.2s;">
                        <i data-lucide="book-open"
                            style="width: 24px; height: 24px; margin: 0 auto 10px; display: block; color: #A0AEC0;"></i>
                        <span
                            style="display: block; font-weight: 700; color: #718096; font-size: 14px; margin-bottom: 4px;">Mühazirə</span>
                        <input type="radio" name="modal_lesson_type" value="lecture" style="display: none;">
                    </div>

                    <div id="card_seminar" class="lesson-type-card" onclick="selectLessonType('seminar')"
                        style="border: 2px solid #EDF2F7; background: #F7FAFC; cursor: pointer; padding: 15px 10px; border-radius: 20px; text-align: center; transition: all 0.2s;">
                        <i data-lucide="users"
                            style="width: 24px; height: 24px; margin: 0 auto 10px; display: block; color: #A0AEC0;"></i>
                        <span
                            style="display: block; font-weight: 700; color: #718096; font-size: 14px; margin-bottom: 4px;">Seminar</span>
                        <input type="radio" name="modal_lesson_type" value="seminar" style="display: none;">
                    </div>

                    <div id="card_laboratory" class="lesson-type-card" onclick="selectLessonType('laboratory')"
                        style="border: 2px solid #EDF2F7; background: #F7FAFC; cursor: pointer; padding: 15px 10px; border-radius: 20px; text-align: center; transition: all 0.2s;">
                        <i data-lucide="beaker"
                            style="width: 24px; height: 24px; margin: 0 auto 10px; display: block; color: #A0AEC0;"></i>
                        <span
                            style="display: block; font-weight: 700; color: #718096; font-size: 14px; margin-bottom: 4px;">Laboratoriya</span>
                        <input type="radio" name="modal_lesson_type" value="laboratory" style="display: none;">
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="live_topic_name"
                    style="font-size: 13px; font-weight: 600; color: #718096; margin-bottom: 8px; display: block;">Mövzu
                    Adı</label>
                <input type="text" id="live_topic_name" class="form-input" placeholder="Məs: Web Dizayn Giriş"
                    style="border: 1px solid #E2E8F0; border-radius: 12px; padding: 14px 16px; width: 100%; outline: none; font-size: 14px; color: #2D3748;"
                    required>
            </div>
        </div>

        <div class="modal-footer"
            style="padding: 24px 30px; background: white; border-top: 1px solid #f1f5f9; display: flex; gap: 16px;">
            <button type="button" onclick="closeStartLiveModal()"
                style="flex: 1; background: #F7FAFC; border: none; color: #4A5568; font-weight: 700; border-radius: 14px; padding: 14px; cursor: pointer; transition: background 0.2s;">
                Ləğv et
            </button>
            <button type="button" onclick="startLiveClass()"
                style="flex: 2; background: #0E5995; border: none; color: white; font-weight: 700; border-radius: 14px; padding: 14px; cursor: pointer; box-shadow: 0 10px 20px rgba(14, 89, 149, 0.2); transition: all 0.2s;">
                Canlı Dərsi Başlat
            </button>
        </div>
    </div>
</div>

<style>
    @keyframes modalSpring {
        0% {
            transform: scale(0.7);
            opacity: 0;
        }

        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    .lesson-type-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
    }

    .lesson-type-card.active {
        background: #f0f9ff !important;
        border-color: #0E5995 !important;
    }

    .lesson-type-card.active span {
        color: #0E5995 !important;
    }

    .lesson-type-card.active i,
    .lesson-type-card.active svg {
        color: #0E5995 !important;
    }

    #live_topic_name:focus {
        border-color: #0E5995 !important;
        box-shadow: 0 0 0 3px rgba(14, 89, 149, 0.05);
    }
</style>

<script>
    function openStartLiveModal(courseId, courseTitle, mTotal, sTotal, mDone, sDone, lTotal, lDone, faculty = '', specialty = '', courseLevel = '-') {
        document.getElementById('live_course_id').value = courseId;
        document.getElementById('live_course_name').value = courseTitle;
        document.getElementById('live_course_level').value = courseLevel;
        document.getElementById('live_faculty_name').value = faculty;
        document.getElementById('live_specialty_name').value = specialty;
        document.getElementById('live_topic_name').value = '';

        const mDoneVal = parseInt(mDone) || 0;
        const sDoneVal = parseInt(sDone) || 0;
        const lDoneVal = parseInt(lDone) || 0;
        const nextLessonNum = mDoneVal + sDoneVal + lDoneVal + 1;

        selectLessonType('lecture');

        document.getElementById('live_topic_name').value = `Dərs ${nextLessonNum} mövzusu`;

        const modal = document.getElementById('startLiveModal');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    function selectLessonType(type) {
        const radios = document.getElementsByName('modal_lesson_type');
        radios.forEach(r => {
            if (r.value === type) r.checked = true;
        });

        const cards = {
            'lecture': document.getElementById('card_lecture'),
            'seminar': document.getElementById('card_seminar'),
            'laboratory': document.getElementById('card_laboratory')
        };

        Object.keys(cards).forEach(key => {
            if (cards[key]) {
                if (key === type) {
                    cards[key].classList.add('active');
                } else {
                    cards[key].classList.remove('active');
                }
            }
        });
    }

    function closeStartLiveModal() {
        document.getElementById('startLiveModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    async function startLiveClass() {
        const courseId = document.getElementById('live_course_id').value;
        const courseName = document.getElementById('live_course_name').value;
        const courseLevel = document.getElementById('live_course_level').value;
        const facultyName = document.getElementById('live_faculty_name').value;
        const specialtyName = document.getElementById('live_specialty_name').value;
        const topicName = document.getElementById('live_topic_name').value.trim();
        const lessonType = document.querySelector('input[name="modal_lesson_type"]:checked').value;

        if (!topicName) {
            alert('Lütfən dərsin mövzusunu daxil edin');
            return;
        }

        const submitBtn = document.querySelector('#startLiveModal button[onclick="startLiveClass()"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i data-lucide="loader" class="animate-spin"></i> Başladılır...';
        if (typeof lucide !== 'undefined') lucide.createIcons();

        try {
            const formData = new FormData();
            formData.append('course_id', courseId);
            formData.append('course_name', courseName);
            formData.append('course_level', courseLevel);
            formData.append('faculty_name', facultyName);
            formData.append('specialty_name', specialtyName);
            formData.append('title', topicName);
            formData.append('lesson_type', lessonType);

            const response = await fetch('api/start_live_class.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.success) {
                window.location.href = `live-studio?id=${result.live_class_id}&subject_id=${courseId}`;
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
</script>