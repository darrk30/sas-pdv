@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel')
<span style="color: #ffffff; font-size: 24px; font-weight: 800; letter-spacing: -0.5px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;">
    tukipu
    <span style="color: #F07020;">.</span>
</span>
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
