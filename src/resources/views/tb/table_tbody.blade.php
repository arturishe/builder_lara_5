@if (count($rows))
    @foreach ($rows as $row)
        <?php $row = $row->toArray();?>
        @include('admin::tb.single_row')
    @endforeach
@else
    <tr><td colspan="100%">No data found</td></tr>
@endif
