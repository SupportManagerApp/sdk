<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Types;

use SupportManager\Sdk\Data\Leave\LeaveEntitlement;
use SupportManager\Sdk\Data\Profile\AddressSuggestion;
use SupportManager\Sdk\Data\Profile\EmergencyContact;
use SupportManager\Sdk\Data\Profile\User;
use SupportManager\Sdk\Data\Tasks\EmployeeTaskProgress;
use SupportManager\Sdk\Data\Tasks\Task;
use SupportManager\Sdk\Http\Download;
use SupportManager\Sdk\Http\DownloadLink;
use SupportManager\Sdk\Resources\Compliance\DBSChecksResource;
use SupportManager\Sdk\Resources\Documents\DocumentsResource;
use SupportManager\Sdk\Resources\Leave\AdminLeaveResource;
use SupportManager\Sdk\Resources\Payroll\PayslipsResource;
use SupportManager\Sdk\Resources\Profile\ProfileResource;
use SupportManager\Sdk\Resources\Signing\SignatureRequestsResource;
use SupportManager\Sdk\Resources\Tasks\TasksResource;

use function PHPStan\Testing\assertType;

/**
 * Not executed — analysed by PHPStan to prove that the downloads return their
 * typed results, and that the base class's readers keep the DTO type they are
 * given all the way out to the public methods built on them.
 */
function probe_downloads(
    PayslipsResource $payslips,
    ProfileResource $profile,
    DocumentsResource $documents,
    DBSChecksResource $dbs,
    SignatureRequestsResource $signing,
): void {
    // File bodies.
    assertType(Download::class, $payslips->download(1));
    assertType(Download::class, $profile->employmentConfirmationLetter());
    assertType(Download::class, $signing->file(1));
    assertType('string|null', $payslips->download(1)->filename());
    assertType('string', $payslips->download(1)->saveTo('/tmp'));

    // Links.
    assertType(DownloadLink::class, $documents->download(1));
    assertType(DownloadLink::class, $dbs->downloadCertificate(1));
    assertType(DownloadLink::class, $signing->downloadSigned(1));
    assertType('string', $documents->download(1)->url);
}

function probe_readers(TasksResource $tasks, ProfileResource $profile, AdminLeaveResource $admin): void
{
    // data*() keep the DTO class they were handed.
    assertType(Task::class.'|null', $tasks->complete('payroll.bank_details'));
    assertType('list<'.EmployeeTaskProgress::class.'>', $tasks->managerInbox());
    assertType('SupportManager\Sdk\Http\Paginated<'.Task::class.'>', $tasks->all());
    assertType('list<'.AddressSuggestion::class.'>', $profile->addressSuggestions('SW1A'));

    // item()/collection() given another DTO class.
    assertType(User::class.'|null', $profile->me());
    assertType('list<'.EmergencyContact::class.'>', $profile->emergencyContacts());
    assertType(LeaveEntitlement::class.'|null', $admin->createEntitlement([]));
}
