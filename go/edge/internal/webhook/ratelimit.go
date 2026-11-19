package webhook

import (
	"sync"
	"time"
)

const (
	defaultCleanupInterval = 30 * time.Second
	defaultBucketIdleTTL   = 10 * time.Minute
)

// RateLimiter is a per-key token bucket. Each key refills at
// RateLimitPerSecond tokens per second up to a burst of the same size, so a
// short spike of provider retries is tolerated while a flood is rejected.
//
// A zero or negative rate disables limiting. Idle buckets are swept
// periodically, which keeps memory bounded when project IDs come and go.
type RateLimiter struct {
	mu      sync.Mutex
	rate    float64
	burst   float64
	buckets map[string]*bucket
	now     func() time.Time
	lastGC  time.Time
	gcEvery time.Duration
	idleTTL time.Duration
}

type bucket struct {
	tokens float64
	last   time.Time
}

// NewRateLimiter builds a limiter refilling perSecond tokens per second.
func NewRateLimiter(perSecond int, now func() time.Time) *RateLimiter {
	if now == nil {
		now = time.Now
	}

	if perSecond < 0 {
		perSecond = 0
	}

	return &RateLimiter{
		rate:    float64(perSecond),
		burst:   float64(perSecond),
		buckets: make(map[string]*bucket),
		now:     now,
		gcEvery: defaultCleanupInterval,
		idleTTL: defaultBucketIdleTTL,
	}
}

// Allow consumes one token for key and reports whether the request may pass.
func (l *RateLimiter) Allow(key string) bool {
	if l.rate <= 0 {
		return true
	}

	l.mu.Lock()
	defer l.mu.Unlock()

	now := l.now()

	if l.lastGC.IsZero() {
		l.lastGC = now
	} else if now.Sub(l.lastGC) >= l.gcEvery {
		l.cleanupLocked(now)
		l.lastGC = now
	}

	entry, exists := l.buckets[key]
	if !exists {
		// A fresh key starts with a full burst, so the first request passes.
		entry = &bucket{tokens: l.burst, last: now}
		l.buckets[key] = entry
	} else {
		elapsed := now.Sub(entry.last).Seconds()
		if elapsed > 0 {
			entry.tokens += elapsed * l.rate
			if entry.tokens > l.burst {
				entry.tokens = l.burst
			}
		}
	}

	entry.last = now

	if entry.tokens < 1 {
		return false
	}

	entry.tokens--

	return true
}

// cleanupLocked drops buckets that have not been touched for idleTTL. A bucket
// that stayed full and idle is indistinguishable from a brand new one, so
// dropping it does not weaken the limit.
func (l *RateLimiter) cleanupLocked(now time.Time) {
	for key, entry := range l.buckets {
		if now.Sub(entry.last) > l.idleTTL {
			delete(l.buckets, key)
		}
	}
}

// size reports the number of tracked buckets; used by tests to assert cleanup.
func (l *RateLimiter) size() int {
	l.mu.Lock()
	defer l.mu.Unlock()

	return len(l.buckets)
}
