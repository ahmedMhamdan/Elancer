<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetAccount;
use App\Mcp\Tools\GetMyProposal;
use App\Mcp\Tools\GetProject;
use App\Mcp\Tools\GetReceivedProposal;
use App\Mcp\Tools\ListMyProjects;
use App\Mcp\Tools\ListMyProposals;
use App\Mcp\Tools\ListReceivedProposals;
use App\Mcp\Tools\SaveProposal;
use App\Mcp\Tools\SearchProjects;
use App\Mcp\Tools\WithdrawProposal;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * Plan 033: Elancer for members' own AI agents, at POST /mcp. Each tool runs the rules the pages and the
 * API (plan 028) run; a token sees only the tools it may use.
 */
#[Name('Elancer')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    Elancer is a freelance marketplace in English and Arabic. Clients post fixed-price projects and hire one freelancer per project; freelancers apply with proposals. This connection acts for one member, within what they allowed.

    - Start with get_account: it says who the member is, whether they may apply and what this connection may do.
    - As a freelancer: search_projects and get_project find work; save_proposal keeps a private draft (action "save") or sends it to the client (action "submit"); withdraw_proposal takes a submitted proposal back. Always ask the member before submitting or withdrawing.
    - Every proposal has a version. Send the version you last read; a stale version is refused, so read the proposal again and retry.
    - As a client: list_my_projects, list_received_proposals and get_received_proposal read the member's projects and the proposals they received. They change nothing.
    - Messages, final offers, contracts, payments and settings are not available here; the member uses the website for them.
    - Payments on Elancer run in test mode: no real money moves.
    - Amounts are in US dollars. Dates are ISO 8601 in UTC.
    MARKDOWN)]
class ElancerServer extends Server
{
    protected array $tools = [
        GetAccount::class,
        SearchProjects::class,
        GetProject::class,
        ListMyProposals::class,
        GetMyProposal::class,
        SaveProposal::class,
        WithdrawProposal::class,
        ListMyProjects::class,
        ListReceivedProposals::class,
        GetReceivedProposal::class,
    ];
}
