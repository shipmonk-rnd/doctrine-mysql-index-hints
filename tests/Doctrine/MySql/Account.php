<?php declare(strict_types = 1);

namespace ShipMonk\Doctrine\MySql;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;

#[Table(name: Account::TABLE_NAME)]
#[Entity]
class Account
{

    public const TABLE_NAME = 'account';

    #[Id]
    #[Column(type: 'string', nullable: false)]
    #[GeneratedValue]
    public int $id;

    #[ManyToOne(targetEntity: User::class, inversedBy: 'managedAccounts')]
    #[JoinColumn(nullable: false)]
    public User $manager;

    public function __construct(
        int $id,
        User $manager,
    )
    {
        $this->id = $id;
        $this->manager = $manager;
    }

}
