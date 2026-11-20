<?php
declare(strict_types=1);

namespace Trejjam\MailChimp\Group;

use GuzzleHttp\Exception\ClientException;
use Nette\Utils\JsonException;
use Trejjam\MailChimp\Entity\Lists\ListItem;
use Trejjam\MailChimp\Entity\Lists\Lists as EntityLists;
use Trejjam\MailChimp\Entity\Lists\Member\Lists as EntityMemberLists;
use Trejjam\MailChimp\Entity\Lists\Member\MemberItem;
use Trejjam\MailChimp\Entity\Lists\Segment\Lists as EntitySegmentLists;
use Trejjam\MailChimp\Entity\Lists\Segment\Segment;
use Trejjam\MailChimp\Exception\ListNotFoundException;
use Trejjam\MailChimp\Exception\MemberNotFoundException;
use Trejjam\MailChimp\Exception\RequestException;
use Trejjam\MailChimp\Exception\SegmentNameTooLongException;
use Trejjam\MailChimp\PaginationOption;
use Trejjam\MailChimp\Request;

final class Lists
{
    private const GROUP_PREFIX = 'lists';
    private const GROUP_MEMBER_PREFIX = '/members';
    private const GROUP_SEGMENT_PREFIX = '/segments';
    private const SEGMENT_NAME_MAX_LENGTH = 100;

    public function __construct(
        private readonly Request $apiRequest
    ) {
    }

    /**
     * @throws JsonException
     */
    public function getAll(?PaginationOption $paginationOption = null) : EntityLists
    {
        return $this->apiRequest->getTyped($this->getEndpointPath(), EntityLists::class, $paginationOption);
    }

    public function getAllIterator(string $listId) : \Generator
    {
        $paginationOption = new PaginationOption();

        $lists = $this->getAll($paginationOption);
        $totalListsCount = $lists->total_items;
        $yelded = 0;

        while (true) {
            foreach ($lists->getLists() as $list) {
                $yelded++;
                yield $list;
            }

            if ($yelded < $totalListsCount) {
                $paginationOption = $paginationOption->nextPage();
                $lists = $this->getAll($paginationOption);
                $totalListsCount = $lists->total_items;
            }
            else {
                break;
            }
        }
    }

    /**
     * @throws JsonException
     * @throws ListNotFoundException
     */
    public function get(string $listId) : ListItem
    {
        try {
            return $this->apiRequest->getTyped($this->getListEndpointPath($listId), ListItem::class);
        }
        catch (ClientException $clientException) {
            throw new ListNotFoundException("List '{$listId}' not found", $clientException);
        }
    }

    /**
     * @throws JsonException
     * @throws ListNotFoundException
     */
    public function getMembers(string $listId, ?PaginationOption $paginationOption = null) : EntityMemberLists
    {
        try {
            return $this->apiRequest->getTyped($this->getMemberEndpointPath($listId), EntityMemberLists::class, $paginationOption);
        }
        catch (ClientException $clientException) {
            throw new ListNotFoundException("List '{$listId}' not found", $clientException);
        }
    }

    public function getMembersIterator(string $listId) : \Generator
    {
        $paginationOption = new PaginationOption();

        $segments = $this->getMembers($listId, $paginationOption);
        $totalMemberCount = $segments->total_items;
        $yelded = 0;

        while (true) {
            foreach ($segments->getMembers() as $member) {
                $yelded++;
                yield $member;
            }

            if ($yelded < $totalMemberCount) {
                $paginationOption = $paginationOption->nextPage();
                $segments = $this->getMembers($listId, $paginationOption);
                $totalMemberCount = $segments->total_items;
            }
            else {
                break;
            }
        }
    }

    /**
     * @throws JsonException
     * @throws MemberNotFoundException
     */
    public function getMember(string $listId, string $memberHash) : MemberItem
    {
        try {
            return $this->apiRequest->getTyped($this->getOneMemberEndpointPath($listId, $memberHash), MemberItem::class);
        }
        catch (ClientException $clientException) {
            throw new MemberNotFoundException("Member '{$memberHash}' not found in the list '{$listId}'", $clientException);
        }
    }

    /**
     * @throws JsonException
     * @throws MemberNotFoundException
     */
    public function addMember(MemberItem $memberItem) : MemberItem
    {
        try {
            return $this->apiRequest->putTyped(
                $this->getOneMemberEndpointPath(
                    $memberItem->list_id,
                    $memberItem->id
                ),
                $memberItem->toArray(),
                MemberItem::class
            );
        }
        catch (ClientException $clientException) {
            throw new MemberNotFoundException("Member '{$memberItem->id}' not added into list '{$memberItem->list_id}'", $clientException);
        }
    }

    /**
     * @throws JsonException
     * @throws MemberNotFoundException
     */
    public function updateMember(MemberItem $memberItem) : MemberItem
    {
        try {
            return $this->apiRequest->patchTyped(
                $this->getOneMemberEndpointPath(
                    $memberItem->list_id,
                    $memberItem->id
                ),
                $memberItem->getUpdated(),
                MemberItem::class
            );
        }
        catch (ClientException $clientException) {
            throw new MemberNotFoundException("Member '{$memberItem->id}' not added into list '{$memberItem->list_id}'", $clientException);
        }
    }

    /**
     * @throws JsonException
     * @throws MemberNotFoundException
     * @throws RequestException
     */
    public function removeMember(MemberItem $memberItem) : ?array
    {
        try {
            return $this->apiRequest->delete($this->getOneMemberEndpointPath($memberItem->list_id, $memberItem->id));
        }
        catch (ClientException $clientException) {
            throw new MemberNotFoundException("Member '{$memberItem->id}' not found in the list '{$memberItem->list_id}'", $clientException);
        }
        catch (RequestException $requestException) {
            if ($requestException->getCode() === 204) {
                return null;
            }

            throw $requestException;
        }
    }

    /**
     * @throws JsonException
     * @throws MemberNotFoundException
     * @throws RequestException
     */
    public function removePermanentMember(MemberItem $memberItem) : ?array
    {
        try {
            return $this->apiRequest->post($this->getDeleteOneMemberPermanentEndpointPath($memberItem->list_id, $memberItem->id), []);
        }
        catch (ClientException $clientException) {
            throw new MemberNotFoundException("Member '{$memberItem->id}' not found in the list '{$memberItem->list_id}'", $clientException);
        }
        catch (RequestException $requestException) {
            if ($requestException->getCode() === 204) {
                return null;
            }

            throw $requestException;
        }
    }

    /**
     * @throws JsonException
     * @throws ListNotFoundException
     */
    public function getSegments(string $listId, ?PaginationOption $paginationOption = null) : EntitySegmentLists
    {
        try {
            return $this->apiRequest->getTyped($this->getSegmentEndpointPath($listId), EntitySegmentLists::class, $paginationOption);
        }
        catch (ClientException $clientException) {
            throw new ListNotFoundException("List '{$listId}' not found", $clientException);
        }
    }

    public function getSegmentsIterator(string $listId) : \Generator
    {
        $paginationOption = new PaginationOption();

        $segments = $this->getSegments($listId, $paginationOption);
        $totalSegmentsCount = $segments->total_items;
        $yelded = 0;

        while (true) {
            foreach ($segments->getSegments() as $segment) {
                $yelded++;
                yield $segment;
            }

            if ($yelded < $totalSegmentsCount) {
                $paginationOption = $paginationOption->nextPage();
                $segments = $this->getSegments($listId, $paginationOption);
                $totalSegmentsCount = $segments->total_items;
            }
            else {
                break;
            }
        }
    }

    /**
     * @throws JsonException
     * @throws ListNotFoundException
     */
    public function getSegment(string $listId, int $segmentId) : Segment
    {
        try {
            return $this->apiRequest->getTyped($this->getOneSegmentEndpointPath($listId, $segmentId), Segment::class);
        }
        catch (ClientException $clientException) {
            throw new ListNotFoundException("Segment '{$segmentId}' not found in the list '{$listId}'", $clientException);
        }
    }

    /**
     * @throws JsonException
     * @throws ListNotFoundException
     */
    public function addSegment(string $listId, string $segmentName) : Segment
    {
        // Strings::length is not usable
        if (strlen($segmentName) >= self::SEGMENT_NAME_MAX_LENGTH) {
            throw new SegmentNameTooLongException($segmentName);
        }

        try {
            return $this->apiRequest->postTyped(
                $this->getSegmentEndpointPath($listId),
                [
                    'name' => $segmentName,
                    'static_segment' => [],
                ],
                Segment::class
            );
        }
        catch (ClientException $clientException) {
            throw new ListNotFoundException("Segment '{$segmentName}' not created in the list '{$listId}'", $clientException);
        }
    }

    /**
     * @throws JsonException
     * @throws MemberNotFoundException
     */
    public function addSegmentMember(int $segmentId, MemberItem $memberItem) : MemberItem
    {
        try {
            return $this->apiRequest->postTyped(
                $this->getOneSegmentEndpointPath($memberItem->list_id, $segmentId) . self::GROUP_MEMBER_PREFIX,
                [
                    'email_address' => $memberItem->email_address,
                    'status' => 'subscribed',
                ],
                MemberItem::class
            );
        }
        catch (ClientException $clientException) {
            throw new MemberNotFoundException("Member '{$memberItem->id}' not added into segment '{$segmentId}'", $clientException);
        }
    }

    private function getEndpointPath() : string
    {
        return self::GROUP_PREFIX;
    }

    private function getListEndpointPath(string $listId) : string
    {
        return $this->getEndpointPath() . "/{$listId}";
    }

    private function getMemberEndpointPath(string $listId) : string
    {
        return $this->getListEndpointPath($listId) . self::GROUP_MEMBER_PREFIX;
    }

    private function getOneMemberEndpointPath(string $listId, string $memberHash) : string
    {
        return $this->getMemberEndpointPath($listId) . "/{$memberHash}";
    }

    private function getDeleteOneMemberPermanentEndpointPath(string $listId, string $memberHash) : string
    {
        return $this->getOneMemberEndpointPath($listId, $memberHash) . '/actions/delete-permanent';
    }

    private function getSegmentEndpointPath(string $listId) : string
    {
        return $this->getListEndpointPath($listId) . self::GROUP_SEGMENT_PREFIX;
    }

    private function getOneSegmentEndpointPath(string $listId, int $segmentId) : string
    {
        return $this->getSegmentEndpointPath($listId) . "/{$segmentId}";
    }
}
