<?php

namespace App\Enums;

enum TeamPermission: string
{
    case ManageAudience = 'audience:manage';
    case ManageContact = 'contact:manage';
    case ManageCompany = 'company:manage';
    case ManageTag = 'tag:manage';
    case ManageMedia = 'media:manage';

    case ManageCampaign = 'campaign:manage';
    case ManageAutomation = 'automation:manage';
    case ManageTransactional = 'transactional:manage';
    case ManageTemplate = 'template:manage';

    case UpdateTeam = 'team:update';
    case DeleteTeam = 'team:delete';

    case AddMember = 'member:add';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';

    case CreateInvitation = 'invitation:create';
    case CancelInvitation = 'invitation:cancel';
}
