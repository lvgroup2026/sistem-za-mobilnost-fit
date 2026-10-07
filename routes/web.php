<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MobilityController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UniverzitetController;
use App\Http\Controllers\MobilityImportController;

Route::get('/', function () {
    $user = Auth::user();

    if (!$user) {
        return redirect()->route('login');
    }

    return match ((int) $user->type) {
        0 => redirect()->route('adminDashboardShow'),
        1 => redirect()->route('profesorDashboardShow'),
    };
})->middleware(['auth', 'verified']);

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware('adminAuth')->prefix('admin')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'adminDashboard'])->name('adminDashboardShow');

    Route::get('/mobilnost', [MobilityController::class, 'index'])->name('admin.mobility');
    Route::post('/mobilnost', [MobilityController::class, 'upload'])->name('admin.mobility.upload');
    Route::post('/mobilnost/export', [MobilityController::class, 'export'])->name('admin.mobility.export');
    Route::post('/mobility/save', [MobilityController::class, 'save'])->name('admin.mobility.save');
    Route::get('/mobility/student-subjects', [MobilityController::class, 'getStudentSubjects'])->name('admin.mobility.student-subjects');
    Route::get('/mobility/faculty-subjects', [MobilityController::class, 'getFacultySubjects'])->name('admin.mobility.faculty-subjects');
    Route::get('/mobility/categories', [MobilityController::class, 'getCategories'])->name('admin.mobility.categories');
    Route::post('/mobility/categories', [MobilityController::class, 'storeCategory'])->name('admin.mobility.categories.store');
    Route::delete('/mobility/categories/{id}', [MobilityController::class, 'destroyCategory'])->name('admin.mobility.categories.destroy');
    
    Route::get('/students/{id}/documents', [App\Http\Controllers\StudentDocumentController::class, 'index'])->name('students.documents.index');
    Route::post('/students/{id}/documents', [App\Http\Controllers\StudentDocumentController::class, 'store'])->name('students.documents.store');
    Route::get('/documents/{id}/download', [App\Http\Controllers\StudentDocumentController::class, 'download'])->name('students.documents.download');
    Route::delete('/documents/{id}', [App\Http\Controllers\StudentDocumentController::class, 'destroy'])->name('students.documents.destroy');

    Route::get('/mobility/import', [MobilityImportController::class, 'index'])->name('admin.mobility.import');
    Route::match(['get', 'post'], '/mobility/import/preview', [MobilityImportController::class, 'preview'])->name('admin.mobility.import.preview');
    Route::post('/mobility/import/store', [MobilityImportController::class, 'import'])->name('admin.mobility.import.store');

    Route::get('/mobility/{id}', [MobilityController::class, 'show'])->name('admin.mobility.show');
    Route::post('/mobility/grade/{id}', [MobilityController::class, 'updateGrade'])->name('admin.mobility.update-grade');
    Route::post('/mobility/{id}/grades', [MobilityController::class, 'updateGrades'])->name('admin.mobility.update-grades');
    Route::post('/mobility/{id}/export-word', [MobilityController::class, 'exportWord'])->name('admin.mobility.export-word');
    Route::post('/mobility/{id}/lock', [MobilityController::class, 'lock'])->name('admin.mobility.lock');

    Route::get('/mobility/{id}/documents', [MobilityController::class, 'documents'])->name('admin.mobility.documents');
    Route::post('/mobility/{id}/documents', [MobilityController::class, 'uploadDocument'])->name('admin.mobility.documents.upload');
    Route::get('/mobility/{id}/documents/{docId}/download', [MobilityController::class, 'downloadDocument'])->name('admin.mobility.documents.download');
    Route::delete('/mobility/{id}/documents/{docId}', [MobilityController::class, 'deleteDocument'])->name('admin.mobility.documents.delete');
    Route::get('/mobility/{id}/documents/zip', [MobilityController::class, 'exportZip'])->name('admin.mobility.documents.zip');

    Route::delete('/mobilnost/{id}', [MobilityController::class, 'destroy'])->name('admin.mobility.destroy');

    // Integracija sa studentskom platformom
    Route::get('/platforma/status', [\App\Http\Controllers\PlatformaController::class, 'status'])->name('platforma.status');
    Route::get('/platforma/studenti', [\App\Http\Controllers\PlatformaController::class, 'pretraga'])->name('platforma.studenti');
    Route::post('/platforma/studenti/{platformaId}/povezi', [\App\Http\Controllers\PlatformaController::class, 'povezi'])->name('platforma.studenti.povezi');
    Route::post('/platforma/studenti/{id}/osvjezi', [\App\Http\Controllers\PlatformaController::class, 'osvjezi'])->name('platforma.studenti.osvjezi');
    Route::post('/platforma/sync-predmeti', [\App\Http\Controllers\PlatformaController::class, 'syncPredmeti'])->name('platforma.sync-predmeti');
    Route::post('/platforma/mobilnost/{id}/posalji', [\App\Http\Controllers\PlatformaController::class, 'posaljiMobilnost'])->name('platforma.mobilnost.posalji');
    Route::post('/platforma/prepis/{id}/posalji', [\App\Http\Controllers\PlatformaController::class, 'posaljiPrepis'])->name('platforma.prepis.posalji');

    Route::get('/users/', [UserController::class, 'index'])->name('users.index');
    Route::post('/users/', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{id}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::get('/students', [App\Http\Controllers\StudentController::class, 'index'])->name('students.index');
    Route::get('/students/create', [App\Http\Controllers\StudentController::class, 'create'])->name('students.create');
    Route::post('/students', [App\Http\Controllers\StudentController::class, 'store'])->name('students.store');
    Route::get('/students/{id}', [App\Http\Controllers\StudentController::class, 'show'])->whereNumber('id')->name('students.show');
    Route::get('/students/{id}/edit', [App\Http\Controllers\StudentController::class, 'edit'])->name('students.edit');
    Route::put('/students/{id}', [App\Http\Controllers\StudentController::class, 'update'])->name('students.update');
    Route::post('/students/{id}/upload-tor', [App\Http\Controllers\StudentController::class, 'uploadTor'])->name('students.upload-tor');
    Route::post('/students/parse-tor', [App\Http\Controllers\StudentController::class, 'parseTor'])->name('students.parse-tor');
    Route::delete('/students/{id}', [App\Http\Controllers\StudentController::class, 'destroy'])->name('students.destroy');

    Route::get('/prepisi/match', [\App\Http\Controllers\PrepisController::class, 'match'])->name('prepis.match');
    Route::post('/prepisi/match', [\App\Http\Controllers\PrepisController::class, 'storeMatch'])->name('prepis.match.store');
    Route::get('/prepisi/student-subjects/{student}', [\App\Http\Controllers\PrepisController::class, 'getStudentSubjects'])->name('prepis.student-subjects');

    Route::get('/prepisi/mapping-request/{id}', [\App\Http\Controllers\PrepisController::class, 'showMappingRequest'])->name('prepis.mapping-request.show');
    Route::post('/prepisi/mapping-request/subject/{id}/update', [\App\Http\Controllers\PrepisController::class, 'updateMappingRequestSubject'])->name('prepis.mapping-request.subject.update');
    Route::delete('/prepisi/mapping-request/subject/{id}/remove', [\App\Http\Controllers\PrepisController::class, 'removeMappingRequestSubject'])->name('prepis.mapping-request.subject.remove');
    Route::post('/prepisi/mapping-request/{id}/accept', [\App\Http\Controllers\PrepisController::class, 'acceptMappingRequest'])->name('prepis.mapping-request.accept');
    Route::post('/prepisi/mapping-request/{id}/reject', [\App\Http\Controllers\PrepisController::class, 'rejectMappingRequest'])->name('prepis.mapping-request.reject');
    Route::post('/prepisi/mapping-request/{id}/add-subject', [\App\Http\Controllers\PrepisController::class, 'addMappingRequestSubject'])->name('prepis.mapping-request.subject.add');
    Route::post('/prepisi/mapping-request/{id}/add-subjects-bulk', [\App\Http\Controllers\PrepisController::class, 'storeBulkSubjects'])->name('prepis.mapping-request.subject.bulk-add');
    Route::delete('/prepisi/mapping-request/{id}', [\App\Http\Controllers\PrepisController::class, 'destroyMappingRequest'])->name('prepis.mapping-request.destroy');
    Route::post('/prepisi/mapping-request/{id}/export-word', [\App\Http\Controllers\PrepisController::class, 'exportWord'])->name('prepis.mapping-request.export-word');
    Route::post('/prepisi/mapping-request/{id}/export-solution', [\App\Http\Controllers\PrepisController::class, 'exportSolution'])->name('prepis.mapping-request.export-solution');

    Route::resource('prepisi', \App\Http\Controllers\PrepisController::class)->names('prepis')->except(['create', 'store']);

    Route::get('/izvjestaji', [\App\Http\Controllers\IzvjestajiController::class, 'index'])->name('izvjestaji.index');
    Route::get('/izvjestaji/export/{type}', [\App\Http\Controllers\IzvjestajiController::class, 'export'])->name('izvjestaji.export');

    Route::get('/fakulteti', [\App\Http\Controllers\FakultetController::class, 'index'])->name('fakulteti.index');
    Route::post('/fakulteti', [\App\Http\Controllers\FakultetController::class, 'store'])->name('fakulteti.store');
    Route::put('/fakulteti/{id}', [\App\Http\Controllers\FakultetController::class, 'update'])->name('fakulteti.update');
    Route::get('/fakulteti/{id}/download', [\App\Http\Controllers\FakultetController::class, 'downloadFile'])->name('fakulteti.download');
    Route::delete('/fakulteti/{id}', [\App\Http\Controllers\FakultetController::class, 'destroy'])->name('fakulteti.destroy');

    Route::post('/fakulteti/{fakultet}/predmeti/import', [\App\Http\Controllers\PredmetController::class, 'import'])->name('fakulteti.predmeti.import');
    Route::get('/fakulteti/{fakultet}/predmeti', [\App\Http\Controllers\PredmetController::class, 'index'])->name('fakulteti.predmeti.index');
    Route::get('/api/fakulteti/{fakultet}/predmeti', [\App\Http\Controllers\PredmetController::class, 'getSubjectsByFaculty'])->name('api.fakulteti.predmeti');

    Route::get('/users/{id}/subjects', [App\Http\Controllers\ProfesorPredmetController::class, 'index'])->name('users.subjects.index');
    Route::post('/users/{id}/subjects', [App\Http\Controllers\ProfesorPredmetController::class, 'store'])->name('users.subjects.store');
    Route::delete('/users/{id}/subjects/{predmet_id}', [App\Http\Controllers\ProfesorPredmetController::class, 'destroy'])->name('users.subjects.destroy');

    Route::post('/predmeti', [\App\Http\Controllers\PredmetController::class, 'store'])->name('predmeti.store');
    Route::put('/predmeti/{id}', [\App\Http\Controllers\PredmetController::class, 'update'])->name('predmeti.update');
    Route::delete('/predmeti/{id}', [\App\Http\Controllers\PredmetController::class, 'destroy'])->name('predmeti.destroy');

    Route::get('/api/fakulteti/{fakultet}/predmeti', [\App\Http\Controllers\PredmetController::class, 'getSubjectsByFaculty'])->name('api.fakulteti.predmeti');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/predmeti/{predmet}/nastavna-lista', [\App\Http\Controllers\NastavnaListaController::class, 'index'])->name('nastavne-liste.index');
    Route::post('/predmeti/{predmet}/nastavna-lista', [\App\Http\Controllers\NastavnaListaController::class, 'store'])->name('nastavne-liste.store');
    Route::get('/nastavna-lista/{nastavnaLista}/download', [\App\Http\Controllers\NastavnaListaController::class, 'download'])->name('nastavne-liste.download');
    Route::delete('/nastavna-lista/{nastavnaLista}', [\App\Http\Controllers\NastavnaListaController::class, 'destroy'])->name('nastavne-liste.destroy');
});

Route::middleware('profesorAuth')->prefix('profesor')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'profesorDashboard'])->name('profesorDashboardShow');

    Route::get('/mobilnost', [MobilityController::class, 'index'])->name('profesor.mobility');
    Route::post('/mobilnost', [MobilityController::class, 'upload'])->name('profesor.mobility.upload');
    Route::post('/mobilnost/export', [MobilityController::class, 'export'])->name('profesor.mobility.export');
    Route::post('/mobility/save', [MobilityController::class, 'save'])->name('profesor.mobility.save');

    Route::post('/prepis-agreement/{id}/accept', [App\Http\Controllers\PrepisAgreementController::class, 'accept'])->name('prepis-agreement.accept');
    Route::post('/prepis-agreement/{id}/reject', [App\Http\Controllers\PrepisAgreementController::class, 'reject'])->name('prepis-agreement.reject');

    Route::get('/mapping-request/{id}', [\App\Http\Controllers\MappingRequestController::class, 'show'])->name('mapping-request.show');
    Route::post('/mapping-request/{id}', [\App\Http\Controllers\MappingRequestController::class, 'update'])->name('mapping-request.update');
});

require __DIR__ . '/auth.php';