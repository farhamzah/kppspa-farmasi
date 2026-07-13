<?php

use App\Http\Controllers\Admin\UserImportController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Examiner\ExamScheduleController as ExaminerExamScheduleController;
use App\Http\Controllers\Examiner\AssessmentController as ExaminerAssessmentController;
use App\Http\Controllers\FieldSupervisor\AssessmentController as FieldAssessmentController;
use App\Http\Controllers\FieldSupervisor\CompetencyController as FieldCompetencyController;
use App\Http\Controllers\FieldSupervisor\FinalReportReviewController as FieldFinalReportReviewController;
use App\Http\Controllers\FieldSupervisor\FieldStudentController;
use App\Http\Controllers\FieldSupervisor\LogbookValidationController;
use App\Http\Controllers\InternalSupervisor\ExamScheduleController as InternalExamScheduleController;
use App\Http\Controllers\InternalSupervisor\AssessmentController as InternalAssessmentController;
use App\Http\Controllers\InternalSupervisor\CompetencyController as InternalCompetencyController;
use App\Http\Controllers\InternalSupervisor\FinalReportReviewController;
use App\Http\Controllers\InternalSupervisor\LogbookMonitoringController as InternalLogbookMonitoringController;
use App\Http\Controllers\InternalSupervisor\SupervisedStudentController;
use App\Http\Controllers\Management\KpAssignmentController;
use App\Http\Controllers\Management\KpAssignmentLogController;
use App\Http\Controllers\Management\KpCompetencyController;
use App\Http\Controllers\Management\KpPeriodController;
use App\Http\Controllers\Management\KpPlaceController;
use App\Http\Controllers\Management\KpPlaceQuotaController;
use App\Http\Controllers\Management\KpDocumentRequirementController;
use App\Http\Controllers\Management\KpRegistrationReviewController;
use App\Http\Controllers\Management\KpQuotaLogController;
use App\Http\Controllers\Management\FinalReportLogController;
use App\Http\Controllers\Management\FinalReportMonitoringController;
use App\Http\Controllers\Management\ExamLogController;
use App\Http\Controllers\Management\ExamRequestController as ManagementExamRequestController;
use App\Http\Controllers\Management\ExamScheduleController as ManagementExamScheduleController;
use App\Http\Controllers\Management\ExternalDocumentReferenceController;
use App\Http\Controllers\Management\ExportController;
use App\Http\Controllers\Management\IntegrationReviewController;
use App\Http\Controllers\Management\AssessmentComponentController;
use App\Http\Controllers\Management\LogbookLogController;
use App\Http\Controllers\Management\LogbookMonitoringController;
use App\Http\Controllers\Management\OrientationTestResultController;
use App\Http\Controllers\Management\PlaceSelectionMonitoringController;
use App\Http\Controllers\Management\SelectionLogController;
use App\Http\Controllers\Management\ScoreLogController;
use App\Http\Controllers\Management\ScoreMonitoringController;
use App\Http\Controllers\Management\RecapController;
use App\Http\Controllers\Management\WaitingListController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleSelectionController;
use App\Http\Controllers\Student\KpDocumentUploadController;
use App\Http\Controllers\Student\KpDocumentController;
use App\Http\Controllers\Student\KpRegistrationController;
use App\Http\Controllers\Student\AssignmentController;
use App\Http\Controllers\Student\ExamRequestController as StudentExamRequestController;
use App\Http\Controllers\Student\FinalReportController;
use App\Http\Controllers\Student\LogbookController;
use App\Http\Controllers\Student\OrientationTestController;
use App\Http\Controllers\Student\PlaceSelectionController;
use App\Http\Controllers\Student\ScoreController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/pilih-role', [RoleSelectionController::class, 'index'])->name('role.select');
    Route::post('/set-role/{role:name}', [RoleSelectionController::class, 'store'])->name('role.set');
    Route::get('/profile/avatar', [ProfileController::class, 'avatar'])->name('profile.avatar.show');
    Route::get('/profile/core-avatar', [ProfileController::class, 'coreAvatar'])->name('profile.core-avatar.show');

    Route::middleware('role.selected')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'redirect'])->name('dashboard');
        Route::get('/profil-saya', [ProfileController::class, 'show'])->name('profile.show');
        Route::redirect('/profile', '/profil-saya')->name('profile.alias');
        Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
        Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');
        Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::post('/profile/sync-core', [ProfileController::class, 'syncCore'])->name('profile.sync-core');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

        Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
            Route::post('users/sync-core', [UserManagementController::class, 'bulkSyncFromCore'])->name('users.sync-core');
            Route::resource('users', UserManagementController::class);
            Route::post('users/{user}/sync-core', [UserManagementController::class, 'syncFromCore'])->name('users.sync-core.single');
            Route::post('users/{user}/reset-password', [UserManagementController::class, 'resetPassword'])->name('users.reset-password');
            Route::post('users/{user}/toggle-status', [UserManagementController::class, 'toggleStatus'])->name('users.toggle-status');

            Route::get('import-users', [UserImportController::class, 'index'])->name('import-users.index');
            Route::post('import-users/preview', [UserImportController::class, 'preview'])->name('import-users.preview');
            Route::post('import-users/process', [UserImportController::class, 'process'])->name('import-users.process');
            Route::get('import-users/history', [UserImportController::class, 'history'])->name('import-users.history');
            Route::get('import-users/history/{batch}', [UserImportController::class, 'show'])->name('import-users.history.show');
            Route::get('import-users/template/{type}', [UserImportController::class, 'template'])->name('import-users.template');
        });

        Route::middleware('role:admin,koordinator_kp')->prefix('management')->name('management.')->group(function () {
            Route::resource('kp-periods', KpPeriodController::class);
            Route::resource('kp-places', KpPlaceController::class);
            Route::resource('kp-place-quotas', KpPlaceQuotaController::class);
            Route::post('kp-place-quotas/{quota}/toggle-open', [KpPlaceQuotaController::class, 'toggleOpen'])->name('kp-place-quotas.toggle-open');
            Route::get('kp-quota-logs', [KpQuotaLogController::class, 'index'])->name('kp-quota-logs.index');
            Route::resource('document-requirements', KpDocumentRequirementController::class)->except(['show']);
            Route::get('kp-registrations', [KpRegistrationReviewController::class, 'index'])->name('kp-registrations.index');
            Route::get('kp-registrations/{registration}', [KpRegistrationReviewController::class, 'show'])->name('kp-registrations.show');
            Route::post('kp-registrations/{registration}/documents/{document}/approve', [KpRegistrationReviewController::class, 'approveDocument'])->name('kp-registrations.documents.approve');
            Route::post('kp-registrations/{registration}/documents/{document}/revision', [KpRegistrationReviewController::class, 'revisionDocument'])->name('kp-registrations.documents.revision');
            Route::post('kp-registrations/{registration}/documents/{document}/reject', [KpRegistrationReviewController::class, 'rejectDocument'])->name('kp-registrations.documents.reject');
            Route::post('kp-registrations/{registration}/verify', [KpRegistrationReviewController::class, 'verify'])->name('kp-registrations.verify');
            Route::post('kp-registrations/{registration}/revision', [KpRegistrationReviewController::class, 'revision'])->name('kp-registrations.revision');
            Route::post('kp-registrations/{registration}/reject', [KpRegistrationReviewController::class, 'reject'])->name('kp-registrations.reject');
            Route::get('kp-registrations/{registration}/documents/{document}/preview', [KpRegistrationReviewController::class, 'preview'])->name('kp-registrations.documents.preview');
            Route::get('kp-registrations/{registration}/documents/{document}/download', [KpRegistrationReviewController::class, 'download'])->name('kp-registrations.documents.download');

            Route::get('place-selections', [PlaceSelectionMonitoringController::class, 'index'])->name('place-selections.index');
            Route::get('place-selections/report/preview', [PlaceSelectionMonitoringController::class, 'reportPreview'])->name('place-selections.report.preview');
            Route::get('place-selections/report/download/{format}', [PlaceSelectionMonitoringController::class, 'reportDownload'])->name('place-selections.report.download');
            Route::get('place-selections/manual', [PlaceSelectionMonitoringController::class, 'manual'])->name('place-selections.manual');
            Route::post('place-selections/manual', [PlaceSelectionMonitoringController::class, 'storeManual'])->name('place-selections.manual.store');
            Route::get('place-selections/{selection}', [PlaceSelectionMonitoringController::class, 'show'])->name('place-selections.show');
            Route::post('place-selections/{selection}/cancel', [PlaceSelectionMonitoringController::class, 'cancel'])->name('place-selections.cancel');
            Route::get('place-selections/{selection}/move', [PlaceSelectionMonitoringController::class, 'move'])->name('place-selections.move');
            Route::post('place-selections/{selection}/move', [PlaceSelectionMonitoringController::class, 'moveStore'])->name('place-selections.move.store');
            Route::get('waiting-lists', [WaitingListController::class, 'index'])->name('waiting-lists.index');
            Route::post('waiting-lists/{waitingList}/cancel', [WaitingListController::class, 'cancel'])->name('waiting-lists.cancel');
            Route::get('selection-logs', [SelectionLogController::class, 'index'])->name('selection-logs.index');
            Route::get('kp-assignments/report/preview', [KpAssignmentController::class, 'reportPreview'])->name('kp-assignments.report.preview');
            Route::get('kp-assignments/report/download/{format}', [KpAssignmentController::class, 'reportDownload'])->name('kp-assignments.report.download');
            Route::resource('kp-assignments', KpAssignmentController::class)->except(['destroy']);
            Route::post('kp-assignments/{assignment}/assign-internal-supervisor', [KpAssignmentController::class, 'assignInternalSupervisor'])->name('kp-assignments.assign-internal-supervisor');
            Route::post('kp-assignments/{assignment}/assign-field-supervisor', [KpAssignmentController::class, 'assignFieldSupervisor'])->name('kp-assignments.assign-field-supervisor');
            Route::post('kp-assignments/{assignment}/cancel', [KpAssignmentController::class, 'cancel'])->name('kp-assignments.cancel');
            Route::post('place-selections/{selection}/create-assignment', [KpAssignmentController::class, 'createFromSelection'])->name('place-selections.create-assignment');
            Route::get('kp-assignment-logs', [KpAssignmentLogController::class, 'index'])->name('kp-assignment-logs.index');
            Route::get('logbooks', [LogbookMonitoringController::class, 'index'])->name('logbooks.index');
            Route::get('logbooks/{logbook}', [LogbookMonitoringController::class, 'show'])->name('logbooks.show');
            Route::post('logbooks/{logbook}/comments', [LogbookMonitoringController::class, 'comments'])->name('logbooks.comments');
            Route::get('logbooks/{logbook}/evidence/download', [LogbookMonitoringController::class, 'download'])->name('logbooks.evidence.download');
            Route::get('logbook-logs', [LogbookLogController::class, 'index'])->name('logbook-logs.index');
            Route::get('final-reports', [FinalReportMonitoringController::class, 'index'])->name('final-reports.index');
            Route::get('final-reports/{report}', [FinalReportMonitoringController::class, 'show'])->name('final-reports.show');
            Route::get('final-reports/files/{file}/download', [FinalReportMonitoringController::class, 'download'])->name('final-reports.files.download');
            Route::get('final-report-logs', [FinalReportLogController::class, 'index'])->name('final-report-logs.index');
            Route::get('exam-requests', [ManagementExamRequestController::class, 'index'])->name('exam-requests.index');
            Route::get('exam-requests/{examRequest}', [ManagementExamRequestController::class, 'show'])->name('exam-requests.show');
            Route::post('exam-requests/{examRequest}/approve', [ManagementExamRequestController::class, 'approve'])->name('exam-requests.approve');
            Route::post('exam-requests/{examRequest}/revision', [ManagementExamRequestController::class, 'revision'])->name('exam-requests.revision');
            Route::post('exam-requests/{examRequest}/reject', [ManagementExamRequestController::class, 'reject'])->name('exam-requests.reject');
            Route::get('exam-requests/{examRequest}/schedule', [ManagementExamScheduleController::class, 'create'])->name('exam-requests.schedule');
            Route::post('exam-requests/{examRequest}/schedule', [ManagementExamScheduleController::class, 'store'])->name('exam-requests.schedule.store');
            Route::get('exams', [ManagementExamScheduleController::class, 'index'])->name('exams.index');
            Route::get('exams/{exam}', [ManagementExamScheduleController::class, 'show'])->name('exams.show');
            Route::get('exams/{exam}/edit', [ManagementExamScheduleController::class, 'edit'])->name('exams.edit');
            Route::put('exams/{exam}', [ManagementExamScheduleController::class, 'update'])->name('exams.update');
            Route::post('exams/{exam}/cancel', [ManagementExamScheduleController::class, 'cancel'])->name('exams.cancel');
            Route::post('exams/{exam}/complete', [ManagementExamScheduleController::class, 'complete'])->name('exams.complete');
            Route::get('exam-logs', [ExamLogController::class, 'index'])->name('exam-logs.index');
            Route::resource('assessment-components', AssessmentComponentController::class)->except(['show'])->parameters(['assessment-components' => 'component']);
            Route::resource('competencies', KpCompetencyController::class)->except(['show', 'create', 'edit'])->parameters(['competencies' => 'competency']);
            Route::get('orientation-tests', [OrientationTestResultController::class, 'index'])->name('orientation-tests.index');
            Route::get('orientation-tests/{attempt}', [OrientationTestResultController::class, 'show'])->name('orientation-tests.show');
            Route::get('scores', [ScoreMonitoringController::class, 'index'])->name('scores.index');
            Route::get('scores/{assignment}', [ScoreMonitoringController::class, 'show'])->name('scores.show');
            Route::post('scores/{assignment}/override', [ScoreMonitoringController::class, 'override'])->name('scores.override');
            Route::post('scores/{assignment}/calculate', [ScoreMonitoringController::class, 'calculate'])->name('scores.calculate');
            Route::post('scores/{assignment}/finalize', [ScoreMonitoringController::class, 'finalize'])->name('scores.finalize');
            Route::post('final-scores/{finalScore}/publish', [ScoreMonitoringController::class, 'publish'])->name('final-scores.publish');
            Route::post('final-scores/{finalScore}/unlock', [ScoreMonitoringController::class, 'unlock'])->name('final-scores.unlock');
            Route::get('score-logs', [ScoreLogController::class, 'index'])->name('score-logs.index');
            Route::get('recaps', [RecapController::class, 'index'])->name('recaps.index');
            Route::get('recaps/{type}/preview', [RecapController::class, 'preview'])->name('recaps.preview');
            Route::get('recaps/{type}/download/{format}', [RecapController::class, 'download'])->name('recaps.download');
            Route::get('recaps/students', [RecapController::class, 'students'])->name('recaps.students');
            Route::get('recaps/placements', [RecapController::class, 'placements'])->name('recaps.placements');
            Route::get('recaps/logbooks', [RecapController::class, 'logbooks'])->name('recaps.logbooks');
            Route::get('recaps/exams', [RecapController::class, 'exams'])->name('recaps.exams');
            Route::get('recaps/scores', [RecapController::class, 'scores'])->name('recaps.scores');
            Route::get('exports/{type}', ExportController::class)->name('exports.download');
            Route::get('integration/tu-payload-preview', [IntegrationReviewController::class, 'tuPayloadPreview'])->name('integration.tu-payload-preview');
            Route::get('integration/tu-payload-preview.json', [IntegrationReviewController::class, 'tuPayloadPreviewJson'])->name('integration.tu-payload-preview.json');
            Route::get('integration/safa-public-info-preview', [IntegrationReviewController::class, 'safaPublicInfoPreview'])->name('integration.safa-public-info-preview');
            Route::get('integration/safa-public-info-preview.json', [IntegrationReviewController::class, 'safaPublicInfoPreviewJson'])->name('integration.safa-public-info-preview.json');
            Route::get('integration/external-document-references', [ExternalDocumentReferenceController::class, 'index'])->name('integration.external-document-references.index');
            Route::post('integration/external-document-references/drafts', [ExternalDocumentReferenceController::class, 'storeDrafts'])->name('integration.external-document-references.store-drafts');
            Route::get('integration/external-document-references/{reference}/edit', [ExternalDocumentReferenceController::class, 'edit'])->name('integration.external-document-references.edit');
            Route::patch('integration/external-document-references/{reference}', [ExternalDocumentReferenceController::class, 'update'])->name('integration.external-document-references.update');
        });

        Route::middleware('role:mahasiswa')->prefix('mahasiswa')->name('student.')->group(function () {
            Route::get('pendaftaran-kp', [KpRegistrationController::class, 'index'])->name('kp-registrations.index');
            Route::get('berkas-kp', [KpDocumentController::class, 'index'])->name('kp-documents.index');
            Route::get('pendaftaran-kp/create', [KpRegistrationController::class, 'create'])->name('kp-registrations.create');
            Route::post('pendaftaran-kp', [KpRegistrationController::class, 'store'])->name('kp-registrations.store');
            Route::get('pendaftaran-kp/{registration}', [KpRegistrationController::class, 'show'])->name('kp-registrations.show');
            Route::post('pendaftaran-kp/{registration}/documents/{requirement}', [KpDocumentUploadController::class, 'store'])->name('kp-registrations.documents.store');
            Route::post('pendaftaran-kp/{registration}/submit', [KpRegistrationController::class, 'submit'])->name('kp-registrations.submit');
            Route::get('pendaftaran-kp/{registration}/documents/{document}/download', [KpRegistrationController::class, 'download'])->name('kp-registrations.documents.download');
            Route::post('pendaftaran-kp/{registration}/cancel', [KpRegistrationController::class, 'cancel'])->name('kp-registrations.cancel');
            Route::get('pemilihan-tempat', [PlaceSelectionController::class, 'index'])->name('place-selections.index');
            Route::get('pemilihan-tempat/{period}', [PlaceSelectionController::class, 'show'])->name('place-selections.show');
            Route::post('pemilihan-tempat/{quota}/pilih', [PlaceSelectionController::class, 'select'])->name('place-selections.select');
            Route::post('pemilihan-tempat/daftar-tunggu', [PlaceSelectionController::class, 'joinWaitingList'])->name('place-selections.waiting-list');
            Route::get('penempatan-kp', [AssignmentController::class, 'show'])->name('assignments.show');
            Route::get('logbook', [LogbookController::class, 'index'])->name('logbooks.index');
            Route::post('logbook/periode-kerja', [LogbookController::class, 'updateWorkPeriod'])->name('logbooks.work-period.update');
            Route::get('logbook/create', [LogbookController::class, 'create'])->name('logbooks.create');
            Route::post('logbook', [LogbookController::class, 'store'])->name('logbooks.store');
            Route::get('logbook/{logbook}', [LogbookController::class, 'show'])->name('logbooks.show');
            Route::get('logbook/{logbook}/edit', [LogbookController::class, 'edit'])->name('logbooks.edit');
            Route::put('logbook/{logbook}', [LogbookController::class, 'update'])->name('logbooks.update');
            Route::post('logbook/{logbook}/submit', [LogbookController::class, 'submit'])->name('logbooks.submit');
            Route::get('logbook/{logbook}/evidence/download', [LogbookController::class, 'download'])->name('logbooks.evidence.download');
            Route::delete('logbook/{logbook}', [LogbookController::class, 'destroy'])->name('logbooks.destroy');
            Route::get('laporan-akhir', [FinalReportController::class, 'show'])->name('final-reports.show');
            Route::post('laporan-akhir/upload', [FinalReportController::class, 'upload'])->name('final-reports.upload');
            Route::post('laporan-akhir/link-final', [FinalReportController::class, 'saveFinalLink'])->name('final-reports.final-link');
            Route::post('laporan-akhir/bimbingan', [FinalReportController::class, 'storeGuidance'])->name('final-reports.guidance.store');
            Route::post('laporan-akhir/submit', [FinalReportController::class, 'submit'])->name('final-reports.submit');
            Route::get('laporan-akhir/files/{file}/download', [FinalReportController::class, 'download'])->name('final-reports.files.download');
            Route::get('sidang', [StudentExamRequestController::class, 'index'])->name('exams.index');
            Route::post('sidang/ajukan', [StudentExamRequestController::class, 'submit'])->name('exams.submit');
            Route::post('sidang/batalkan-pengajuan', [StudentExamRequestController::class, 'cancel'])->name('exams.cancel');
            Route::get('pre-post-test', [OrientationTestController::class, 'index'])->name('orientation-tests.index');
            Route::get('pre-post-test/hasil/{attempt}', [OrientationTestController::class, 'result'])->name('orientation-tests.result');
            Route::get('pre-post-test/{test}', [OrientationTestController::class, 'show'])->name('orientation-tests.show');
            Route::post('pre-post-test/{test}', [OrientationTestController::class, 'submit'])->name('orientation-tests.submit');
            Route::get('nilai', [ScoreController::class, 'show'])->name('scores.show');
        });

        Route::middleware('role:pembimbing_dalam')->prefix('pembimbing-dalam')->name('internal-supervisor.')->group(function () {
            Route::get('mahasiswa-bimbingan', [SupervisedStudentController::class, 'index'])->name('assignments.index');
            Route::get('mahasiswa-bimbingan/{assignment}', [SupervisedStudentController::class, 'show'])->name('assignments.show');
            Route::get('logbook', [InternalLogbookMonitoringController::class, 'index'])->name('logbooks.index');
            Route::get('logbook/{logbook}', [InternalLogbookMonitoringController::class, 'show'])->name('logbooks.show');
            Route::post('logbook/{logbook}/comments', [InternalLogbookMonitoringController::class, 'comments'])->name('logbooks.comments');
            Route::get('logbook/{logbook}/evidence/download', [InternalLogbookMonitoringController::class, 'download'])->name('logbooks.evidence.download');
            Route::get('laporan-akhir', [FinalReportReviewController::class, 'index'])->name('final-reports.index');
            Route::get('laporan-akhir/{report}', [FinalReportReviewController::class, 'show'])->name('final-reports.show');
            Route::post('laporan-akhir/{report}/approve', [FinalReportReviewController::class, 'approve'])->name('final-reports.approve');
            Route::post('laporan-akhir/{report}/revision', [FinalReportReviewController::class, 'revision'])->name('final-reports.revision');
            Route::post('laporan-akhir/{report}/reject', [FinalReportReviewController::class, 'reject'])->name('final-reports.reject');
            Route::post('laporan-akhir/{report}/bimbingan/{guidance}/approve', [FinalReportReviewController::class, 'approveGuidance'])->name('final-reports.guidance.approve');
            Route::post('laporan-akhir/{report}/bimbingan/{guidance}/revision', [FinalReportReviewController::class, 'revisionGuidance'])->name('final-reports.guidance.revision');
            Route::get('laporan-akhir/files/{file}/download', [FinalReportReviewController::class, 'download'])->name('final-reports.files.download');
            Route::get('jadwal-sidang', [InternalExamScheduleController::class, 'index'])->name('exams.index');
            Route::get('jadwal-sidang/{exam}', [InternalExamScheduleController::class, 'show'])->name('exams.show');
            Route::get('penilaian', [InternalAssessmentController::class, 'index'])->name('assessments.index');
            Route::get('penilaian/{assignment}', [InternalAssessmentController::class, 'show'])->name('assessments.show');
            Route::post('penilaian/{assignment}/save', [InternalAssessmentController::class, 'save'])->name('assessments.save');
            Route::post('penilaian/{assignment}/submit', [InternalAssessmentController::class, 'submit'])->name('assessments.submit');
            Route::get('kompetensi', [InternalCompetencyController::class, 'index'])->name('competencies.index');
            Route::get('kompetensi/{assignment}', [InternalCompetencyController::class, 'show'])->name('competencies.show');
        });

        Route::middleware('role:pembimbing_lapangan')->prefix('pembimbing-lapangan')->name('field-supervisor.')->group(function () {
            Route::get('mahasiswa-kp', [FieldStudentController::class, 'index'])->name('assignments.index');
            Route::get('mahasiswa-kp/{assignment}', [FieldStudentController::class, 'show'])->name('assignments.show');
            Route::get('logbook', [LogbookValidationController::class, 'index'])->name('logbooks.index');
            Route::post('logbook/bulk-approve', [LogbookValidationController::class, 'bulkApprove'])->name('logbooks.bulk-approve');
            Route::get('logbook/{logbook}', [LogbookValidationController::class, 'show'])->name('logbooks.show');
            Route::post('logbook/{logbook}/approve', [LogbookValidationController::class, 'approve'])->name('logbooks.approve');
            Route::post('logbook/{logbook}/revision', [LogbookValidationController::class, 'revision'])->name('logbooks.revision');
            Route::post('logbook/{logbook}/reject', [LogbookValidationController::class, 'reject'])->name('logbooks.reject');
            Route::get('logbook/{logbook}/evidence/download', [LogbookValidationController::class, 'download'])->name('logbooks.evidence.download');
            Route::get('laporan-akhir', [FieldFinalReportReviewController::class, 'index'])->name('final-reports.index');
            Route::get('laporan-akhir/{report}', [FieldFinalReportReviewController::class, 'show'])->name('final-reports.show');
            Route::post('laporan-akhir/{report}/approve', [FieldFinalReportReviewController::class, 'approve'])->name('final-reports.approve');
            Route::post('laporan-akhir/{report}/revision', [FieldFinalReportReviewController::class, 'revision'])->name('final-reports.revision');
            Route::post('laporan-akhir/{report}/reject', [FieldFinalReportReviewController::class, 'reject'])->name('final-reports.reject');
            Route::get('laporan-akhir/files/{file}/download', [FieldFinalReportReviewController::class, 'download'])->name('final-reports.files.download');
            Route::get('penilaian', [FieldAssessmentController::class, 'index'])->name('assessments.index');
            Route::get('penilaian/{assignment}', [FieldAssessmentController::class, 'show'])->name('assessments.show');
            Route::post('penilaian/{assignment}/save', [FieldAssessmentController::class, 'save'])->name('assessments.save');
            Route::post('penilaian/{assignment}/submit', [FieldAssessmentController::class, 'submit'])->name('assessments.submit');
            Route::get('kompetensi', [FieldCompetencyController::class, 'index'])->name('competencies.index');
            Route::get('kompetensi/{assignment}', [FieldCompetencyController::class, 'show'])->name('competencies.show');
            Route::put('kompetensi/{assignment}', [FieldCompetencyController::class, 'update'])->name('competencies.update');
        });

        Route::middleware('role:penguji')->prefix('penguji')->name('examiner.')->group(function () {
            Route::get('jadwal-sidang', [ExaminerExamScheduleController::class, 'index'])->name('exams.index');
            Route::get('jadwal-sidang/{exam}', [ExaminerExamScheduleController::class, 'show'])->name('exams.show');
            Route::get('penilaian', [ExaminerAssessmentController::class, 'index'])->name('assessments.index');
            Route::get('penilaian/{exam}', [ExaminerAssessmentController::class, 'show'])->name('assessments.show');
            Route::post('penilaian/{exam}/save', [ExaminerAssessmentController::class, 'save'])->name('assessments.save');
            Route::post('penilaian/{exam}/submit', [ExaminerAssessmentController::class, 'submit'])->name('assessments.submit');
        });

        Route::get('/mahasiswa/dashboard', fn (DashboardController $controller, Request $request) => $controller->show($request, 'mahasiswa'))
            ->middleware('role:mahasiswa')
            ->name('mahasiswa.dashboard');
        Route::get('/admin/dashboard', fn (DashboardController $controller, Request $request) => $controller->show($request, 'admin'))
            ->middleware('role:admin')
            ->name('admin.dashboard');
        Route::get('/koordinator/dashboard', fn (DashboardController $controller, Request $request) => $controller->show($request, 'koordinator_kp'))
            ->middleware('role:koordinator_kp')
            ->name('koordinator.dashboard');
        Route::get('/pembimbing-dalam/dashboard', fn (DashboardController $controller, Request $request) => $controller->show($request, 'pembimbing_dalam'))
            ->middleware('role:pembimbing_dalam')
            ->name('pembimbing-dalam.dashboard');
        Route::get('/pembimbing-lapangan/dashboard', fn (DashboardController $controller, Request $request) => $controller->show($request, 'pembimbing_lapangan'))
            ->middleware('role:pembimbing_lapangan')
            ->name('pembimbing-lapangan.dashboard');
        Route::get('/penguji/dashboard', fn (DashboardController $controller, Request $request) => $controller->show($request, 'penguji'))
            ->middleware('role:penguji')
            ->name('penguji.dashboard');
    });
});
