<?php declare(strict_types = 1);

namespace ShipMonk\Doctrine\MySql;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name=User::TABLE_NAME)
 * @ORM\Entity
 */
class User
{

    public const TABLE_NAME = 'user';

    /**
     * @var int
     * @ORM\Id
     * @ORM\Column(type="string", nullable=false)
     * @ORM\GeneratedValue
     */
    public $id;

    /**
     * @var Account
     * @ORM\ManyToOne(targetEntity="Account")
     * @ORM\JoinColumn(nullable=false)
     */
    public $account;

    /**
     * @var Collection<int,Account>
     * @ORM\OneToMany(targetEntity="Account", mappedBy="manager")
     */
    public $managedAccounts;

    public function __construct(int $id, Account $account)
    {
        $this->id = $id;
        $this->account = $account;
        $this->managedAccounts = new ArrayCollection([$account]);
    }

}
