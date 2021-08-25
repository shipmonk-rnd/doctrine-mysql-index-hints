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
     * @ORM\Id
     * @ORM\Column(type="string", nullable=false)
     * @ORM\GeneratedValue
     */
    public int $id;

    /**
     * @ORM\ManyToOne(targetEntity=Account::class)
     * @ORM\JoinColumn(nullable=false)
     */
    public Account $account;

    /**
     * @var Collection<int,Account>
     * @ORM\OneToMany(targetEntity=Account::class, mappedBy="manager")
     */
    public Collection $managedAccounts;

    public function __construct(int $id, Account $account)
    {
        $this->id = $id;
        $this->account = $account;
        $this->managedAccounts = new ArrayCollection([$account]);
    }

}
