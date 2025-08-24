export default {
  data() {
    return {
      upcomingEvents: [],
      pastWeekEvents: [],
      debugLog: []
    };
  },
  created() {
    this.fetchEvents();
  },
  methods: {
    async fetchEvents() {
      const url = 'https://din-domän.se/eventor_proxy.php';
      this.debugLog.push(`Anropar: ${url}`);

      try {
        const response = await fetch(url);
        const data = await response.json();
        this.debugLog.push(`Svar mottaget med ${data.length} event`);

        const now = new Date();
        const oneWeekAgo = new Date();
        oneWeekAgo.setDate(now.getDate() - 7);

        // Dela upp i kommande och genomförda
        this.upcomingEvents = data.filter(event => {
          const start = new Date(event.startDate);
          return start >= now;
        });

        this.pastWeekEvents = data.filter(event => {
          const start = new Date(event.startDate);
          return start < now && start > oneWeekAgo;
        });

        this.debugLog.push(`Kommande: ${this.upcomingEvents.length}, senaste veckan: ${this.pastWeekEvents.length}`);
      } catch (error) {
        this.debugLog.push(`Fel vid hämtning: ${error.message}`);
        console.error('Error fetching or parsing events:', error);
      }
    },

    formatDate(dateStr) {
      const date = new Date(dateStr);
      return date.toLocaleDateString('sv-SE', {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    },

    formatDateTime(dateStr) {
      const date = new Date(dateStr);
      return date.toLocaleString('sv-SE', {
        dateStyle: 'short',
        timeStyle: 'short'
      });
    },
      
    showOrdinaryDeadline(event) {
      return event.ordinaryDeadline && new Date(event.ordinaryDeadline) > new Date();
    },
      
    showLateDeadline(event) {
      return event.lateDeadline && new Date(event.ordinaryDeadline) <= new Date() && new Date(event.lateDeadline) >= new Date();
    }

  }
}
