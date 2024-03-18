<?php declare(strict_types = 1);

namespace ShipMonk\Doctrine\MySql;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;

#[Table(name: User::TABLE_NAME)]
#[Entity]
class User
{

    public const TABLE_NAME = 'user';

    #[Id]
    #[Column(type: 'string', nullable: false)]
    #[GeneratedValue]
    public int $id;

    #[ManyToOne(targetEntity: Account::class, inversedBy: 'managedAccounts')]
    #[JoinColumn(nullable: false)]
    public Account $account;

    /**
     * @var Collection<int,Account>
     */
    #[OneToMany(targetEntity: Account::class, mappedBy: 'manager')]
    public Collection $managedAccounts;

    public function __construct(int $id, Account $account)
    {
        $this->id = $id;
        $this->account = $account;
        $this->managedAccounts = new ArrayCollection([$account]);
    }

}
