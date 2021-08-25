<?php declare(strict_types = 1);

namespace ShipMonk\Doctrine\MySql;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name=Account::TABLE_NAME)
 * @ORM\Entity
 */
class Account
{

    public const TABLE_NAME = 'account';

    /**
     * @ORM\Id
     * @ORM\Column(type="string", nullable=false)
     * @ORM\GeneratedValue
     */
    public int $id;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="managedAccounts")
     * @ORM\JoinColumn(nullable=false)
     */
    public User $manager;

    public function __construct(int $id, User $manager)
    {
        $this->id = $id;
        $this->manager = $manager;
    }

}
