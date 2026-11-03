<div class="space-y-5">
    <x-order::order-amounts
        :total="$total"
        :paid="$paid"
    />

    <x-order::order-form
        :$userCars
        :$itemCatalog
        :$users
        :$order
    />
</div>
