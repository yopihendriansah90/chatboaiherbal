@php
    $conversation = $this->conversation();
    $identity = $conversation->identity;
    $messagesByDate = $conversation->messages->groupBy(fn ($message) => $message->occurred_at->toDateString());
    $initial = mb_strtoupper(mb_substr($conversation->contact->display_name, 0, 1));
@endphp

<x-filament-panels::page>
    <div
        class="chat-monitor"
        wire:poll.5s="refreshConversation"
        x-data="{
            stickToBottom: true,
            scrollToBottom() {
                const box = this.$refs.messages;
                if (box) box.scrollTop = box.scrollHeight;
            },
            trackScroll() {
                const box = this.$refs.messages;
                if (! box) return;
                this.stickToBottom = (box.scrollHeight - box.scrollTop - box.clientHeight) < 100;
            },
        }"
        x-init="$nextTick(() => scrollToBottom())"
        x-on:conversation-updated.window="$nextTick(() => { if (stickToBottom) scrollToBottom() })"
    >
        <section class="chat-profile-card">
            <div class="chat-avatar">{{ $initial }}</div>

            <div class="chat-profile-main">
                <div class="chat-profile-title-row">
                    <h2>{{ $conversation->contact->display_name }}</h2>
                    <span class="chat-channel-badge">{{ ucfirst($conversation->channel) }}</span>
                    <span @class(['chat-status-badge', 'is-active' => $conversation->status === 'active'])>
                        {{ match ($conversation->service_status) {
                            'bot_active' => 'Ditangani bot',
                            'waiting_agent' => 'Menunggu agen',
                            'assigned' => 'Ditangani agen',
                            'waiting_customer' => 'Menunggu pelanggan',
                            'resolved' => 'Selesai',
                            default => ucfirst($conversation->service_status),
                        } }}
                    </span>
                </div>

                <div class="chat-profile-meta">
                    @if ($identity?->username)
                        <span>{{ '@' . $identity->username }}</span>
                    @endif
                    <span>Chat ID: {{ $identity?->external_chat_id ?? $conversation->external_conversation_id }}</span>
                    <span>{{ $conversation->message_count }} pesan</span>
                    @if ($conversation->last_message_at)
                        <span>Terakhir {{ $conversation->last_message_at->diffForHumans() }}</span>
                    @endif
                    @if ($conversation->assignee)
                        <span>Agen: {{ $conversation->assignee->name }}</span>
                    @endif
                </div>
            </div>

            <div class="chat-summary-tags">
                @if ($conversation->category)
                    <span>Kategori: {{ $conversation->category }}</span>
                @endif
                @if ($conversation->product_code)
                    <span>Produk: {{ $conversation->product_code }}</span>
                @endif
                @if ($conversation->is_emergency)
                    <span class="is-emergency">Tanda darurat</span>
                @endif
                @foreach ($conversation->tags ?? [] as $tag)
                    <span>#{{ $tag }}</span>
                @endforeach
                @if ($conversation->sla_due_at)
                    <span>SLA {{ $conversation->sla_due_at->diffForHumans() }}</span>
                @endif
            </div>
        </section>

        <section class="chat-window">
            <header class="chat-window-header">
                <div>
                    <strong>Riwayat percakapan</strong>
                    <span>Mode pantau · diperbarui otomatis setiap 5 detik</span>
                </div>
                <span class="chat-live-indicator"><i></i> Live</span>
            </header>

            <div class="chat-messages" x-ref="messages" x-on:scroll.passive="trackScroll()">
                @forelse ($messagesByDate as $date => $messages)
                    <div class="chat-date-separator">
                        <span>
                            @if ($date === now()->toDateString())
                                Hari ini
                            @elseif ($date === now()->subDay()->toDateString())
                                Kemarin
                            @else
                                {{ \Carbon\Carbon::parse($date)->translatedFormat('d F Y') }}
                            @endif
                        </span>
                    </div>

                    @foreach ($messages as $message)
                        @php($incoming = $message->direction === 'incoming')
                        <article @class(['chat-row', 'is-user' => $incoming, 'is-bot' => ! $incoming])>
                            <div class="chat-bubble">
                                <div class="chat-sender">{{ $incoming ? $conversation->contact->display_name : (($message->metadata['source'] ?? null) === 'agent' ? 'Customer Service' : 'Chatbot Herbal') }}</div>
                                <div class="chat-text">{!! $this->formattedMessage($message->content) !!}</div>
                                <div class="chat-message-meta">
                                    <time title="{{ $message->occurred_at->format('d M Y H:i:s') }}">
                                        {{ $message->occurred_at->format('H:i') }}
                                    </time>

                                    @if (! $incoming)
                                        <span @class(['chat-delivery', 'is-failed' => $message->delivery_status === 'failed'])>
                                            @if ($message->delivery_status === 'failed')
                                                Gagal
                                            @elseif ($message->delivery_status === 'delivered')
                                                ✓✓
                                            @else
                                                ✓
                                            @endif
                                        </span>
                                    @endif
                                </div>

                                @if ($message->error_code)
                                    <div class="chat-error">{{ $message->error_code }}</div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                @empty
                    <div class="chat-empty">
                        <strong>Belum ada pesan</strong>
                        <span>Pesan baru akan muncul otomatis di halaman ini.</span>
                    </div>
                @endforelse
            </div>
        </section>

        @if ($conversation->notes->isNotEmpty() || $conversation->events->isNotEmpty())
            <section class="chat-audit-grid">
                <div class="chat-audit-card">
                    <strong>Catatan internal</strong>
                    @forelse ($conversation->notes->sortByDesc('id') as $note)
                        <p>{{ $note->content }} <small>— {{ $note->user?->name ?? 'Sistem' }}, {{ $note->created_at->format('d M H:i') }}</small></p>
                    @empty
                        <p>Belum ada catatan.</p>
                    @endforelse
                </div>
                <div class="chat-audit-card">
                    <strong>Audit tindakan</strong>
                    @foreach ($conversation->events->sortByDesc('occurred_at')->take(20) as $event)
                        <p>{{ str($event->type)->replace('_', ' ')->title() }} <small>— {{ $event->user?->name ?? 'Sistem' }}, {{ $event->occurred_at->format('d M H:i') }}</small></p>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    <style>
        .chat-monitor { display: grid; gap: 1rem; }
        .chat-profile-card, .chat-window { border: 1px solid rgba(127, 127, 127, .22); background: var(--gray-50, #fff); border-radius: 1rem; overflow: hidden; }
        .dark .chat-profile-card, .dark .chat-window { background: rgb(24 24 27); border-color: rgb(63 63 70); }
        .chat-profile-card { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.25rem; }
        .chat-avatar { display: grid; place-items: center; flex: 0 0 3rem; width: 3rem; height: 3rem; border-radius: 999px; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; font-size: 1.15rem; font-weight: 700; }
        .chat-profile-main { flex: 1; min-width: 0; }
        .chat-profile-title-row { display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; }
        .chat-profile-title-row h2 { margin: 0; font-size: 1.1rem; font-weight: 700; }
        .chat-channel-badge, .chat-status-badge, .chat-summary-tags span { padding: .2rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 600; background: rgb(229 231 235); color: rgb(55 65 81); }
        .dark .chat-channel-badge, .dark .chat-status-badge, .dark .chat-summary-tags span { background: rgb(63 63 70); color: rgb(228 228 231); }
        .chat-status-badge.is-active { background: rgb(220 252 231); color: rgb(21 128 61); }
        .dark .chat-status-badge.is-active { background: rgba(34, 197, 94, .16); color: rgb(134 239 172); }
        .chat-profile-meta { display: flex; flex-wrap: wrap; gap: .4rem 1rem; margin-top: .35rem; color: rgb(107 114 128); font-size: .78rem; }
        .chat-summary-tags { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .4rem; max-width: 30%; }
        .chat-summary-tags .is-emergency { background: rgb(254 226 226); color: rgb(185 28 28); }
        .chat-window-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .8rem 1rem; border-bottom: 1px solid rgba(127, 127, 127, .2); }
        .chat-window-header div { display: flex; flex-direction: column; }
        .chat-window-header span { color: rgb(107 114 128); font-size: .75rem; }
        .chat-live-indicator { display: inline-flex; align-items: center; gap: .35rem; }
        .chat-live-indicator i { width: .5rem; height: .5rem; border-radius: 999px; background: #22c55e; box-shadow: 0 0 0 .2rem rgba(34, 197, 94, .15); }
        .chat-messages { min-height: 30rem; max-height: calc(100vh - 21rem); overflow-y: auto; padding: 1.1rem; background-color: rgb(245 247 250); background-image: radial-gradient(rgba(100, 116, 139, .09) 1px, transparent 1px); background-size: 18px 18px; scroll-behavior: smooth; }
        .dark .chat-messages { background-color: rgb(12 15 20); background-image: radial-gradient(rgba(148, 163, 184, .08) 1px, transparent 1px); }
        .chat-date-separator { display: flex; justify-content: center; margin: .5rem 0 1rem; }
        .chat-date-separator span { padding: .25rem .65rem; border-radius: 999px; background: rgba(255, 255, 255, .9); color: rgb(100 116 139); font-size: .7rem; box-shadow: 0 1px 2px rgba(0, 0, 0, .08); }
        .dark .chat-date-separator span { background: rgba(39, 39, 42, .95); color: rgb(161 161 170); }
        .chat-row { display: flex; margin: .35rem 0; }
        .chat-row.is-user { justify-content: flex-end; }
        .chat-row.is-bot { justify-content: flex-start; }
        .chat-bubble { position: relative; max-width: min(72%, 46rem); padding: .55rem .75rem .35rem; border-radius: .85rem; box-shadow: 0 1px 2px rgba(15, 23, 42, .12); }
        .is-user .chat-bubble { background: rgb(219 234 254); color: rgb(30 58 138); border-bottom-right-radius: .2rem; }
        .is-bot .chat-bubble { background: white; color: rgb(31 41 55); border-bottom-left-radius: .2rem; }
        .dark .is-user .chat-bubble { background: rgb(30 64 95); color: rgb(239 246 255); }
        .dark .is-bot .chat-bubble { background: rgb(39 39 42); color: rgb(244 244 245); }
        .chat-sender { margin-bottom: .2rem; font-size: .7rem; font-weight: 700; color: rgb(37 99 235); }
        .is-bot .chat-sender { color: rgb(22 163 74); }
        .chat-text { white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.45; font-size: .9rem; }
        .chat-text a { color: rgb(37 99 235); text-decoration: underline; text-underline-offset: 2px; }
        .dark .chat-text a { color: rgb(125 211 252); }
        .chat-message-meta { display: flex; align-items: center; justify-content: flex-end; gap: .25rem; margin-top: .2rem; color: currentColor; opacity: .6; font-size: .65rem; }
        .chat-delivery { font-weight: 700; }
        .chat-delivery.is-failed, .chat-error { color: rgb(220 38 38); opacity: 1; }
        .chat-error { margin-top: .25rem; font-size: .68rem; }
        .chat-empty { min-height: 25rem; display: grid; place-content: center; text-align: center; color: rgb(107 114 128); }
        .chat-empty span { display: block; margin-top: .25rem; font-size: .8rem; }
        .chat-audit-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
        .chat-audit-card { padding: 1rem; border: 1px solid rgba(127, 127, 127, .22); border-radius: 1rem; background: var(--gray-50, #fff); }
        .chat-audit-card p { margin: .65rem 0 0; font-size: .82rem; }
        .chat-audit-card small { color: rgb(107 114 128); }
        .dark .chat-audit-card { background: rgb(24 24 27); border-color: rgb(63 63 70); }
        @media (max-width: 768px) {
            .chat-profile-card { align-items: flex-start; flex-wrap: wrap; }
            .chat-summary-tags { max-width: none; width: 100%; justify-content: flex-start; padding-left: 4rem; }
            .chat-messages { min-height: 28rem; max-height: calc(100vh - 18rem); padding: .75rem; }
            .chat-bubble { max-width: 88%; }
            .chat-audit-grid { grid-template-columns: 1fr; }
        }
    </style>
</x-filament-panels::page>
