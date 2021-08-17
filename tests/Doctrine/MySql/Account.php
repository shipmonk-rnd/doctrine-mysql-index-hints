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
     * @var int
     * @ORM\Id
     * @ORM\Column(type="string", nullable=false)
     * @ORM\GeneratedValue
     */
    public $id;

    /**
     * @var User
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="managedAccounts")
     * @ORM\JoinColumn(nullable=false)
     */
    public $manager;

    public function __construct(int $id, User $manager)
    {
        $this->id = $id;
        $this->manager = $manager;
    }

}
