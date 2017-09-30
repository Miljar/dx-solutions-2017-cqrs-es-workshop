<?php

namespace Specification;

use Behat\Behat\Context\Context;
use Building\Domain\Aggregate\Building;
use Building\Domain\DomainEvent\CheckInAnomalyDetected;
use Building\Domain\DomainEvent\NewBuildingWasRegistered;
use Building\Domain\DomainEvent\UserCheckedIn;
use Prooph\EventSourcing\AggregateChanged;
use Prooph\EventSourcing\EventStoreIntegration\AggregateTranslator;
use Prooph\EventStore\Aggregate\AggregateType;
use Rhumsaa\Uuid\Uuid;

final class CheckInCheckOut implements Context
{
    /**
     * @var Uuid
     */
    private $buildingId;

    /**
     * @var Building|null
     */
    private $aggregate;

    /**
     * @var AggregateChanged[]
     */
    private $pastEvents = [];

    /**
     * @var AggregateChanged[]|null
     */
    private $recordedEvents;

    /**
     * @Given a building has been registered
     */
    public function a_building_has_been_registered()
    {
        $this->buildingId = Uuid::uuid4();
        $this->recordPastEvent(NewBuildingWasRegistered::occur(
            $this->buildingId->toString(),
            ['name' => 'DX Solutions']
        ));
    }

    /**
     * @Given /^the "([^"]+)" has been registered as a building$/
     */
    public function the_given_building_has_been_registered_as_a_building(string $buildingName)
    {
        $this->buildingId = Uuid::uuid4();
        $this->recordPastEvent(NewBuildingWasRegistered::occur(
            $this->buildingId->toString(),
            ['name' => $buildingName]
        ));
    }

    /**
     * @Given the user checked into the building
     */
    public function the_user_checked_into_the_building()
    {
        $this->recordPastEvent(UserCheckedIn::fromBuildingAndUser(
            $this->buildingId,
            'Joe'
        ));
    }

    /**
     * @When the user checks into the building
     */
    public function the_user_checks_into_the_building()
    {
        $this->building()->checkInUser('Joe');
    }


    /**
     * @When /^"([^"]+)" checks into the building$/
     */
    public function the_given_user_checks_into_the_building(string $username)
    {
        $this->building()->checkInUser($username);
    }

    /**
     * @Then the user was checked into the building
     */
    public function the_user_was_checked_into_the_building()
    {
        if (! $this->popLastRecordedEvent() instanceof UserCheckedIn) {
            throw new \UnexpectedValueException();
        }
    }

    /**
     * @Then /^"([^"]+)" was checked into the building$/
     */
    public function the_given_user_was_checked_into_the_building(string $username)
    {
        $event = $this->popLastRecordedEvent();

        if (! $event instanceof UserCheckedIn) {
            throw new \UnexpectedValueException();
        }

        if ($event->username() !== $username) {
            throw new \UnexpectedValueException();
        }
    }

    /**
     * @Then a check-in anomaly was detected
     */
    public function a_check_in_anomaly_was_detected()
    {
        if (! $this->popLastRecordedEvent() instanceof CheckInAnomalyDetected) {
            throw new \UnexpectedValueException();
        }
    }

    private function recordPastEvent(AggregateChanged $event)
    {
        $this->pastEvents[] = $event;
    }

    private function building() : Building
    {
        if (! $this->aggregate) {
            $this->aggregate = (new AggregateTranslator())
                ->reconstituteAggregateFromHistory(
                    AggregateType::fromAggregateRootClass(Building::class),
                    new \ArrayIterator($this->pastEvents)
                );

            $this->pastEvents = [];
        }

        return $this->aggregate;
    }

    private function popLastRecordedEvent() : AggregateChanged
    {
        if (! isset($this->recordedEvents)) {
            $this->recordedEvents = (new AggregateTranslator())
                ->extractPendingStreamEvents($this->building());
        }


        return \array_shift($this->recordedEvents);
    }
}
