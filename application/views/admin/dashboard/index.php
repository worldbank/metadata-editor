<!-- Include Vue.js and Vuetify -->
<link href="<?php echo base_url('vue-app/assets/mdi.min.css'); ?>" rel="stylesheet">
<link href="<?php echo base_url('vue-app/assets/vuetify.min.css'); ?>" rel="stylesheet">

<style>
    .dashboard-component {
        background-color: #f5f5f5;
        min-height: calc(100vh - 160px);
    }
    
    .stats-number {
        font-size: 2.5rem;
        font-weight: 500;
        line-height: 1;
    }
    
    .stats-label {
        font-size: 0.875rem;
        color: #757575;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 8px;
    }
    
    .stats-change {
        font-size: 0.875rem;
        margin-top: 8px;
    }
    
    .activity-item {
        border-left: 3px solid;
        padding: 12px 16px;
        margin-bottom: 8px;
        background-color: white;
    }
    
    .activity-item.project {
        border-left-color: #2196F3;
    }
    
    .activity-item.audit {
        border-left-color: #4CAF50;
    }
    
    .activity-time {
        font-size: 0.75rem;
        color: #9E9E9E;
    }
    
</style>

<script type="text/x-template" id="dashboard-overview-template">
    <v-row>
        <v-col cols="12" sm="6" md="3" class="d-flex">
            <v-card class="flex-grow-1">
                <v-card-text class="text-center pa-4">
                    <v-icon size="48" color="primary">mdi-check-circle-outline</v-icon>
                    <div class="stats-number primary--text">{{ stats.projects.total }}</div>
                    <div class="stats-label"><?php echo t('Total Projects'); ?></div>
                </v-card-text>
            </v-card>
        </v-col>

        <v-col cols="12" sm="6" md="3" class="d-flex">
            <v-card class="flex-grow-1">
                <v-card-text class="text-center pa-4">
                    <v-icon size="48" color="info">mdi-chart-line-variant</v-icon>
                    <div class="stats-number info--text">{{ stats.projects.recent_30_days }}</div>
                    <div class="stats-label"><?php echo t('New Projects (30d)'); ?></div>
                </v-card-text>
            </v-card>
        </v-col>
        
        <v-col cols="12" sm="6" md="3" class="d-flex">
            <v-card class="flex-grow-1">
                <v-card-text class="text-center pa-4">
                    <v-icon size="48" color="success">mdi-account-group-outline</v-icon>
                    <div class="stats-number success--text">{{ stats.users.active }}</div>
                    <div class="stats-label"><?php echo t('Active Users'); ?></div>
                    <div class="stats-change success--text" v-if="stats.users.this_month > 0">
                        <v-icon small color="success">mdi-arrow-up</v-icon>
                        {{ stats.users.this_month }} <?php echo t('new users'); ?>
                    </div>
                </v-card-text>
            </v-card>
        </v-col>
        
        <v-col cols="12" sm="6" md="3" class="d-flex">
            <v-card class="flex-grow-1">
                <v-card-text class="text-center pa-4">
                    <v-icon size="48" color="orange">mdi-harddisk-outline</v-icon>
                    <div class="stats-number orange--text">{{ stats.storage.total_size_formatted }}</div>
                    <div class="stats-label"><?php echo t('Storage Used'); ?></div>
                    <div class="stats-change grey--text">
                        {{ stats.storage.file_count }} <?php echo t('files'); ?>
                    </div>
                </v-card-text>
            </v-card>
        </v-col>
    </v-row>
    
</script>

<script type="text/x-template" id="analytics-section-template">
    <div>
        <v-row class="mt-4">
            <v-col cols="12" md="8">
                <v-card>
                    <v-card-title>
                        <v-icon class="mr-2" color="primary">mdi-chart-line</v-icon>
                        <?php echo t('Traffic Overview'); ?>
                        <v-spacer></v-spacer>
                        <span class="text-caption grey--text"><?php echo t('Last 30 days'); ?></span>
                    </v-card-title>
                    <v-divider></v-divider>
                    <v-card-text>
                        <div v-if="hasData">
                            <canvas ref="trafficChartCanvas" height="80"></canvas>
                        </div>
                        <div v-else class="text-center py-8 grey--text">
                            <?php echo t('No analytics data available.'); ?>
                        </div>
                    </v-card-text>
                </v-card>

                <v-card class="mb-4 mt-4">
                    <v-card-title>
                        <v-icon class="mr-2" color="primary">mdi-account-multiple</v-icon>
                        <?php echo t('Top Users'); ?>
                        <v-spacer></v-spacer>
                        <span class="text-caption grey--text"><?php echo t('Today'); ?></span>
                    </v-card-title>
                    <v-divider></v-divider>
                    <v-card-text style="max-height:400px;overflow:auto;">
                        <template v-if="topUsers.length > 0">
                            <div v-for="(user, index) in topUsers" :key="index" class="d-flex justify-space-between align-center mb-3">
                                <div class="d-flex align-center">
                                    <v-avatar color="primary" size="32" class="mr-3">
                                        <span class="white--text text-caption">{{ user.username.substring(0, 2).toUpperCase() }}</span>
                                    </v-avatar>
                                    <div>
                                        <div class="text-body-2 font-weight-medium">{{ user.username }}</div>
                                        <div class="text-caption grey--text">{{ user.email }}</div>
                                    </div>
                                </div>
                                <v-chip small color="primary" outlined>
                                    {{ user.page_views }}
                                </v-chip>
                            </div>
                        </template>
                        <div v-else class="text-center grey--text text-caption py-4">
                            <?php echo t('No user activity tracked'); ?>
                        </div>
                    </v-card-text>
                </v-card>

                <v-card class="mt-4" v-if="slowPages.length > 0">
                    <v-card-title>
                        <v-icon class="mr-2" color="error">mdi-alert-circle</v-icon>
                        <?php echo t('Pages with Slow Load Times'); ?>
                        <v-spacer></v-spacer>
                        <span class="text-caption grey--text"><?php echo t('Last 30 days - Top 10 slowest'); ?></span>
                    </v-card-title>
                    <v-divider></v-divider>
                    <v-card-text>
                        <v-simple-table dense fixed-header style="max-height:400px;overflow:auto;">
                            <thead>
                                <tr>
                                    <th class="text-left" style="width:50%;"><?php echo t('Page'); ?></th>
                                    <th class="text-right"><?php echo t('Avg Load Time'); ?></th>
                                    <th class="text-right"><?php echo t('Server Time'); ?></th>
                                    <th class="text-right"><?php echo t('DOM Ready'); ?></th>
                                    <th class="text-right"><?php echo t('Samples'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(pageItem, index) in slowPages" :key="index" :class="getPerformanceTextColor(pageItem.avg_load_time || 0) + '--text'">
                                    <td>
                                        <code class="text-caption" style="word-break: break-all;">{{ pageItem.page || '(unknown)' }}</code>
                                    </td>
                                    <td class="text-right" >
                                        {{ formatTime(pageItem.avg_load_time || 0) }}
                                    </td>
                                    <td class="text-right">{{ formatTime(pageItem.avg_server_time || 0) }}</td>
                                    <td class="text-right">{{ formatTime(pageItem.avg_dom_ready || 0) }}</td>
                                    <td class="text-right">
                                        <v-chip x-small color="grey" text-color="white">
                                            {{ pageItem.samples || 0 }}
                                        </v-chip>
                                    </td>
                                </tr>
                            </tbody>
                        </v-simple-table>
                    </v-card-text>
                </v-card>
                <v-card class="mt-4" v-else>
                    <v-card-text class="text-center py-8 grey--text">
                        <?php echo t('No slow pages detected. All pages are loading quickly!'); ?>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" md="4">
                <v-card>
                    <v-card-title>
                        <v-icon class="mr-2" color="success">mdi-clock-outline</v-icon>
                        <?php echo t('Today\'s Traffic'); ?>
                        <v-spacer></v-spacer>
                        <v-chip x-small color="success" outlined>
                            <v-icon x-small left>mdi-circle</v-icon>
                            <?php echo t('Live'); ?>
                        </v-chip>
                    </v-card-title>
                    <v-card-subtitle class="text-caption">
                        <?php echo t('Updates every minute'); ?>
                    </v-card-subtitle>
                    <v-divider></v-divider>
                    <v-card-text>
                        <div v-if="hasData">
                            <canvas ref="hourlyChartCanvas" height="80"></canvas>
                        </div>
                        <div v-else class="text-center py-8 grey--text">
                            <?php echo t('No traffic data for today yet.'); ?>
                        </div>
                    </v-card-text>
                </v-card>
                
                <v-card class="mt-4">
                    <v-card-title>
                        <v-icon class="mr-2" color="primary">mdi-file-document</v-icon>
                        <?php echo t('Top Pages'); ?>
                        <v-spacer></v-spacer>
                        <span class="text-caption grey--text"><?php echo t('Today'); ?></span>
                    </v-card-title>
                    <v-divider></v-divider>
                    <v-card-text>
                        <v-simple-table dense fixed-header style="max-height:300px;overflow:auto;">
                            <thead>
                                <tr>
                                    <th><?php echo t('Page'); ?></th>
                                    <th class="text-right"><?php echo t('Views'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-if="topPages.length === 0">
                                    <tr>
                                        <td colspan="2" class="text-center grey--text text-caption">
                                            <?php echo t('No data available'); ?>
                                        </td>
                                    </tr>
                                </template>
                                <template v-else>
                                    <tr v-for="(pageItem, index) in topPages" :key="index">
                                        <td><code class="text-caption">{{ pageItem.page }}</code></td>
                                        <td class="text-right">{{ formatNumber(pageItem.views) }}</td>
                                    </tr>
                                </template>
                            </tbody>
                        </v-simple-table>
                    </v-card-text>
                </v-card>
                
                <v-card class="mt-4">
                    <v-card-text class="pa-3">
                        <div class="d-flex align-center">
                            <div class="text-h5 primary--text mr-3" style="width:50px;">{{ formatNumber(analytics.page_views_today || 0) }}</div>
                            <div>
                                <div class="text-body-2 font-weight-medium"><?php echo t('Page Views Today'); ?></div>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
                
                <v-card class="mt-4">
                    <v-card-text class="pa-3">
                        <div class="d-flex align-center">
                            <div class="text-h5 success--text mr-3" style="width:50px;">{{ formatNumber(analytics.active_users || 0) }}</div>
                            <div>
                                <div class="text-body-2 font-weight-medium"><?php echo t('Active Users'); ?></div>
                                <div class="text-caption grey--text"><?php echo t('Last hour'); ?></div>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
                
                <v-card class="mt-4">
                    <v-card-text class="pa-3">
                        <div class="d-flex align-center">
                            <div class="text-h5 info--text mr-3" style="width:50px;">{{ formatNumber(analytics.sessions_30d || 0) }}</div>
                            <div>
                                <div class="text-body-2 font-weight-medium"><?php echo t('Sessions'); ?></div>
                                <div class="text-caption grey--text"><?php echo t('Last 30 days'); ?></div>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>

                <v-card class="mt-4">
                    <v-card-text class="pa-3">
                        <div class="d-flex align-center">
                            <div class="text-h5 mr-3" :class="(analytics.errors_today || 0) > 0 ? 'error--text' : 'grey--text'" style="width:50px;">{{ formatNumber(analytics.errors_today || 0) }}</div>
                            <div>
                                <div class="text-body-2 font-weight-medium"><?php echo t('Errors Today'); ?></div>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

    </div>
</script>

<script type="text/x-template" id="api-logs-section-template">
    <v-row class="mt-4">
        <v-col cols="12" md="4">
            <v-card>
                <v-card-title>
                    <v-icon class="mr-2" color="primary">mdi-chart-bar</v-icon>
                    <?php echo t('API Logs Overview'); ?>
                </v-card-title>
                <v-divider></v-divider>
                <v-card-text v-if="hasData">
                    <div class="mb-4">
                        <div class="text-caption grey--text mb-1"><?php echo t('Total requests (last'); ?> {{ apiLogs.days }} <?php echo t('days)'); ?></div>
                        <div class="text-h5 font-weight-medium primary--text">{{ formatNumber(totals.total_requests) }}</div>
                    </div>
                    <div class="mb-4">
                        <div class="text-caption grey--text mb-1"><?php echo t('Errors'); ?></div>
                        <div class="text-h6 font-weight-medium" :class="totals.error_rate > 0 ? 'error--text' : 'grey--text'">
                            {{ formatNumber(totals.error_count) }}
                            <span class="text-caption grey--text">({{ formatPercent(totals.error_rate, 2) }})</span>
                        </div>
                    </div>
                    <div>
                        <div class="text-caption grey--text mb-1"><?php echo t('Average response time'); ?></div>
                        <div class="text-body-1 font-weight-medium">{{ formatSeconds(totals.avg_response_time) }}</div>
                    </div>
                </v-card-text>
                <v-card-text v-else class="text-center py-8 grey--text">
                    <?php echo t('No API log data available for the selected period.'); ?>
                </v-card-text>
            </v-card>
        </v-col>

        <v-col cols="12" md="8">
            <v-card class="mb-4">
                <v-card-title>
                    <v-icon class="mr-2" color="info">mdi-api</v-icon>
                    <?php echo t('Top Endpoints'); ?>
                </v-card-title>
                <v-divider></v-divider>
                <v-card-text v-if="hasData && topEndpoints.length > 0">
                    <v-simple-table dense>
                        <template v-slot:default>
                            <thead>
                                <tr>
                                    <th><?php echo t('Method'); ?></th>
                                    <th><?php echo t('Endpoint'); ?></th>
                                    <th class="text-right"><?php echo t('Requests'); ?></th>
                                    <th class="text-right"><?php echo t('Errors'); ?></th>
                                    <th class="text-right"><?php echo t('Avg Time'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(endpoint, index) in topEndpoints" :key="index">
                                    <td>
                                        <v-chip x-small color="primary" text-color="white">{{ endpoint.method }}</v-chip>
                                    </td>
                                    <td><code class="text-caption" style="word-break: break-all;">{{ endpoint.uri }}</code></td>
                                    <td class="text-right">{{ formatNumber(endpoint.total_requests) }}</td>
                                    <td class="text-right" :class="endpoint.error_count > 0 ? 'error--text' : ''">
                                        {{ formatNumber(endpoint.error_count) }}
                                        <span class="text-caption grey--text">({{ formatPercent(endpoint.error_rate, 2) }})</span>
                                    </td>
                                    <td class="text-right">{{ formatSeconds(endpoint.avg_response_time) }}</td>
                                </tr>
                            </tbody>
                        </template>
                    </v-simple-table>
                </v-card-text>
                <v-card-text v-else class="text-center py-8 grey--text">
                    <?php echo t('No endpoint data available.'); ?>
                </v-card-text>
            </v-card>

            <v-card class="mb-4">
                <v-card-title>
                    <v-icon class="mr-2" color="teal">mdi-lan-connect</v-icon>
                    <?php echo t('Top IP Addresses'); ?>
                </v-card-title>
                <v-divider></v-divider>
                <v-card-text v-if="hasData && topIps.length > 0">
                    <v-simple-table dense>
                        <template v-slot:default>
                            <thead>
                                <tr>
                                    <th><?php echo t('IP'); ?></th>
                                    <th class="text-right"><?php echo t('Requests'); ?></th>
                                    <th class="text-right"><?php echo t('Errors'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(ip, index) in topIps" :key="index">
                                    <td><code class="text-caption">{{ ip.ip_address }}</code></td>
                                    <td class="text-right">{{ formatNumber(ip.total_requests) }}</td>
                                    <td class="text-right" :class="ip.error_count > 0 ? 'error--text' : ''">
                                        {{ formatNumber(ip.error_count) }}
                                        <span class="text-caption grey--text">({{ formatPercent(ip.error_rate, 2) }})</span>
                                    </td>
                                </tr>
                            </tbody>
                        </template>
                    </v-simple-table>
                </v-card-text>
                <v-card-text v-else class="text-center py-8 grey--text">
                    <?php echo t('No IP data available.'); ?>
                </v-card-text>
            </v-card>

            <v-card>
                <v-card-title>
                    <v-icon class="mr-2" color="deep-purple">mdi-account-key</v-icon>
                    <?php echo t('Top Users / API Keys'); ?>
                </v-card-title>
                <v-divider></v-divider>
                <v-card-text v-if="hasData && topUsers.length > 0">
                    <v-simple-table dense>
                        <template v-slot:default>
                            <thead>
                                <tr>
                                    <th><?php echo t('User / Key'); ?></th>
                                    <th class="text-right"><?php echo t('Requests'); ?></th>
                                    <th class="text-right"><?php echo t('Errors'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(user, index) in topUsers" :key="index">
                                    <td>
                                        <div class="text-body-2 font-weight-medium">{{ getApiUserDisplay(user) }}</div>
                                        <div v-if="user.api_key" class="text-caption grey--text">{{ user.api_key }}</div>
                                    </td>
                                    <td class="text-right">{{ formatNumber(user.total_requests) }}</td>
                                    <td class="text-right" :class="user.error_count > 0 ? 'error--text' : ''">
                                        {{ formatNumber(user.error_count) }}
                                        <span class="text-caption grey--text">({{ formatPercent(user.error_rate, 2) }})</span>
                                    </td>
                                </tr>
                            </tbody>
                        </template>
                    </v-simple-table>
                </v-card-text>
                <v-card-text v-else class="text-center py-8 grey--text">
                    <?php echo t('No user or API key data available.'); ?>
                </v-card-text>
            </v-card>


        </v-col>

    </v-row>
</script>

<script type="text/x-template" id="activity-section-template">
    <v-row class="mt-4">
        <v-col cols="12" md="6" class="d-flex">
            <v-card class="flex-grow-1 d-flex flex-column">
                <v-card-title>
                    <v-icon class="mr-2" color="primary">mdi-clock-outline</v-icon>
                    <?php echo t('Recent Activity'); ?>
                    <v-spacer></v-spacer>
                    <v-btn text small color="primary" href="<?php echo site_url('admin/audit_logs'); ?>">
                        <?php echo t('View all'); ?>
                        <v-icon right small>mdi-open-in-new</v-icon>
                    </v-btn>
                </v-card-title>
                <v-divider></v-divider>
                <v-card-text class="flex-grow-1">
                    <v-list v-if="activity && activity.length > 0" dense>
                        <v-list-item 
                            v-for="(item, index) in activity" 
                            :key="index"
                            :class="index < activity.length - 1 ? 'mb-2' : ''">
                            <v-list-item-icon>
                                <v-icon :color="item.type === 'project' ? 'blue' : 'green'">
                                    {{ item.type === 'project' ? 'mdi-folder' : 'mdi-history' }}
                                </v-icon>
                            </v-list-item-icon>
                            <v-list-item-content>
                                <v-list-item-title>
                                    <span v-if="item.project_title">
                                        {{ item.project_title }}
                                    </span>
                                    <span v-else>
                                        {{ item.description }}
                                    </span>
                                </v-list-item-title>
                                <v-list-item-subtitle>
                                    {{ formatTimeUnix(item.timestamp) }} • {{ item.user }}
                                </v-list-item-subtitle>
                            </v-list-item-content>
                        </v-list-item>
                    </v-list>
                    <div v-else class="text-center py-8">
                        <v-icon size="64" color="grey lighten-1">mdi-information-outline</v-icon>
                        <p class="grey--text mt-2"><?php echo t('No recent activity'); ?></p>
                    </div>
                </v-card-text>
            </v-card>
        </v-col>

        <v-col cols="12" md="6" class="d-flex">
            <v-card class="flex-grow-1 d-flex flex-column">
                <v-card-title>
                    <v-icon class="mr-2" color="success">mdi-account-group</v-icon>
                    <?php echo t('User Overview'); ?>
                    <v-spacer></v-spacer>
                    <v-btn text small color="primary" href="<?php echo site_url('admin/users'); ?>">
                        <?php echo t('Manage users'); ?>
                        <v-icon right small>mdi-open-in-new</v-icon>
                    </v-btn>
                </v-card-title>
                <v-divider></v-divider>
                <v-card-text class="pa-4 flex-grow-1">
                    <div class="d-flex align-center mb-3">
                        <div class="text-h5 font-weight-medium primary--text mr-4" style="width: 80px;">{{ formatNumber(users.total) }}</div>
                        <div class="d-flex align-center">
                            <v-icon class="mr-2" color="primary">mdi-account</v-icon>
                            <div>
                                <div class="text-body-2 grey--text"><?php echo t('Total users'); ?></div>
                            </div>
                        </div>
                    </div>

                    <v-divider class="my-3"></v-divider>

                    <div class="d-flex align-center mb-3">
                        <div class="text-h5 font-weight-medium success--text mr-4" style="width: 80px;">{{ formatNumber(users.active) }}</div>
                        <div class="d-flex align-center">
                            <v-icon class="mr-2" color="success">mdi-account-check</v-icon>
                            <div>
                                <div class="text-body-2 grey--text"><?php echo t('Active users (last 30 days)'); ?></div>
                            </div>
                        </div>
                    </div>

                    <v-divider class="my-3"></v-divider>

                    <div class="d-flex align-center mb-3">
                        <div class="text-h5 font-weight-medium info--text mr-4" style="width: 80px;">{{ formatNumber(users.this_month) }}</div>
                        <div class="d-flex align-center">
                            <v-icon class="mr-2" color="info">mdi-account-plus</v-icon>
                            <div>
                                <div class="text-body-2 grey--text"><?php echo t('New users (30 days)'); ?></div>
                            </div>
                        </div>
                    </div>

                    <v-divider class="my-3"></v-divider>

                    <div class="d-flex align-center mb-3">
                        <div class="text-h5 font-weight-medium grey--text mr-4" style="width: 80px;">{{ formatNumber(users.no_projects) }}</div>
                        <div class="d-flex align-center">
                            <v-icon class="mr-2" color="grey">mdi-account-off</v-icon>
                            <div>
                                <div class="text-body-2 grey--text"><?php echo t('Users without projects'); ?></div>
                            </div>
                        </div>
                    </div>

                    <v-divider class="my-3"></v-divider>

                    <div class="d-flex align-center">
                        <div class="text-h5 font-weight-medium warning--text mr-4" style="width: 80px;">{{ formatNumber(inactiveUsersCount) }}</div>
                        <div class="d-flex align-center">
                            <v-icon class="mr-2" color="warning">mdi-account-alert</v-icon>
                            <div>
                                <div class="text-body-2 grey--text"><?php echo t('Pending activation'); ?></div>
                            </div>
                        </div>
                    </div>
                </v-card-text>
            </v-card>
        </v-col>
    </v-row>
</script>

<script type="text/x-template" id="system-status-section-template">
    <v-row class="mt-4">
        <v-col cols="12" md="6">
            <v-card>
                <v-card-title>
                    <v-icon class="mr-2" color="info">mdi-information-outline</v-icon>
                    <?php echo t('System Information'); ?>
                </v-card-title>
                <v-divider></v-divider>
                <v-card-text>
                    <v-row>
                        <v-col cols="12" md="6">
                            <div class="text-caption grey--text mb-1"><?php echo t('PHP Version'); ?></div>
                            <div class="text-body-1 font-weight-medium">{{ system.php_version }}</div>
                        </v-col>
                        <v-col cols="12" md="6">
                            <div class="text-caption grey--text mb-1"><?php echo t('Operating System'); ?></div>
                            <div class="text-body-1 font-weight-medium">{{ system.os_type }}</div>
                        </v-col>
                        <v-col cols="12" md="6">
                            <div class="text-caption grey--text mb-1"><?php echo t('PHP Memory Limit'); ?></div>
                            <div class="text-body-1 font-weight-medium">{{ system.memory_limit }}</div>
                        </v-col>
                        <v-col cols="12" md="6">
                            <div class="text-caption grey--text mb-1"><?php echo t('Upload Limit'); ?></div>
                            <div class="text-body-1 font-weight-medium">{{ system.upload_limit }}</div>
                        </v-col>
                        <v-col cols="12" md="6">
                            <div class="text-caption grey--text mb-1"><?php echo t('Post Limit'); ?></div>
                            <div class="text-body-1 font-weight-medium">{{ system.post_limit }}</div>
                        </v-col>
                        <v-col cols="12" md="6">
                            <div class="text-caption grey--text mb-1"><?php echo t('PHP Timezone'); ?></div>
                            <div class="text-body-1 font-weight-medium">{{ system.timezone }}</div>
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>
        </v-col>

        <v-col cols="12" md="6">
            <v-card>
                <v-card-title>
                    <v-icon class="mr-2" color="primary">mdi-api</v-icon>
                    <?php echo t('FastAPI Status'); ?>
                </v-card-title>
                <v-divider></v-divider>
                <v-card-text class="pa-4">
                    <div class="d-flex align-center mb-3">
                        <v-icon size="32" class="mr-3" :color="fastapiStatus.color">{{ fastapiStatus.icon }}</v-icon>
                        <div>
                            <div class="text-h5 font-weight-medium">{{ fastapiStatus.label }}</div>
                            <div class="text-body-2 grey--text">{{ fastapiStatus.message }}</div>
                        </div>
                    </div>

                    <v-divider class="my-3"></v-divider>

                    <div class="text-caption grey--text mb-1"><?php echo t('FastAPI URL'); ?></div>
                    <div class="text-body-2 font-weight-medium mb-2">
                        <code>{{ fastapiStatus.base_url || '<?php echo t('Not configured'); ?>' }}</code>
                    </div>

                    <div class="text-caption grey--text mb-1"><?php echo t('Queue Size'); ?></div>
                    <div class="text-body-1 font-weight-medium mb-3">
                        {{ queueSize !== null ? queueSize : '<?php echo t('N/A'); ?>' }}
                    </div>

                    <div class="text-caption grey--text mb-1"><?php echo t('Recent Jobs'); ?></div>
                    <div v-if="hasFastApiJobs" class="mb-3" style="max-height:300px;overflow:auto;">
                        <v-simple-table dense>
                            <template v-slot:default>
                                <thead>
                                    <tr>
                                        <th><?php echo t('Job ID'); ?></th>
                                        <th><?php echo t('Type'); ?></th>
                                        <th><?php echo t('Status'); ?></th>
                                        <th><?php echo t('Created'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="job in fastapiJobs" :key="job.jobid">
                                        <td><code class="text-caption">{{ job.jobid }}</code></td>
                                        <td class="text-caption">{{ job.jobtype }}</td>
                                        <td class="text-caption">{{ job.status }}</td>
                                        <td class="text-caption">{{ formatIsoDate(job.created_at) }}</td>
                                    </tr>
                                </tbody>
                            </template>
                        </v-simple-table>
                    </div>
                    <div v-else class="text-body-2 grey--text mb-3"><?php echo t('No active jobs.'); ?></div>

                    <div v-if="fastapiStatus.details" class="text-caption grey--text mb-1"><?php echo t('Raw Response'); ?></div>
                    <pre v-if="fastapiStatus.details" class="grey lighten-4 pa-3" style="max-height: 150px; overflow:auto;">{{ fastapiStatus.details }}</pre>
                </v-card-text>
            </v-card>
        </v-col>

        <v-col cols="12">
            <v-card>
                <v-card-title>
                    <v-icon class="mr-2" color="orange">mdi-harddisk</v-icon>
                    <?php echo t('Disk Space Usage'); ?>
                </v-card-title>
                <v-divider></v-divider>
                <v-card-text v-if="diskSpace && diskSpace.available">
                    <v-row>
                        <v-col cols="12" md="6">
                            <div class="text-caption grey--text mb-1"><?php echo t('Total space'); ?></div>
                            <div class="text-body-1 font-weight-medium">{{ diskSpace.total_formatted }}</div>
                        </v-col>
                        <v-col cols="12" md="6">
                            <div class="text-caption grey--text mb-1"><?php echo t('Used space'); ?></div>
                            <div class="text-body-1 font-weight-medium">{{ diskSpace.used_formatted }}</div>
                        </v-col>
                        <v-col cols="12" md="6">
                            <div class="text-caption grey--text mb-1"><?php echo t('Free space'); ?></div>
                            <div class="text-body-1 font-weight-medium">{{ diskSpace.free_formatted }}</div>
                        </v-col>
                        <v-col cols="12" md="6">
                            <div class="text-caption grey--text mb-1"><?php echo t('Usage'); ?></div>
                            <v-progress-linear 
                                :value="diskSpace.percentage" 
                                :color="diskSpaceColor" 
                                height="20"
                                rounded>
                                <template v-slot:default>
                                    <strong class="white--text">{{ diskSpace.percentage }}%</strong>
                                </template>
                            </v-progress-linear>
                        </v-col>
                        <v-col cols="12">
                            <div class="text-caption grey--text">
                                <v-icon small>mdi-folder-outline</v-icon>
                                <?php echo t('Storage Path'); ?>: {{ diskSpace.storage_path }}
                            </div>
                        </v-col>
                    </v-row>
                </v-card-text>
                <v-card-text v-else class="text-center py-8">
                    <v-icon size="64" color="grey lighten-1">mdi-alert-circle-outline</v-icon>
                    <p class="grey--text mt-2">{{ diskSpace && diskSpace.error ? diskSpace.error : '<?php echo t('Disk space information unavailable'); ?>' }}</p>
                </v-card-text>
            </v-card>
        </v-col>
    </v-row>
</script>

<script type="text/x-template" id="dashboard-home-template">
    <v-container class="pa-4">

        <v-row class="mb-4">
            <v-col cols="12" class="d-flex justify-space-between align-center">
                <div>
                    <h1 class="text-h4 font-weight-medium"><?php echo t('Dashboard'); ?></h1>
                    <p class="text-caption grey--text mt-1" v-if="lastUpdated">
                        <v-icon small>mdi-clock-outline</v-icon>
                        <?php echo t('Last updated'); ?>: {{ lastUpdated }}
                    </p>
                </div>
                <div class="d-flex align-center">
                    <v-btn class="mr-2" text color="primary" :to="{ path: '/api-log-aggregates' }" href="#/api-log-aggregates">
                        <v-icon left>mdi-traffic-light</v-icon>
                    <?php echo t('API Log Aggregates'); ?>
                    </v-btn>
                    <v-btn class="mr-2" text color="primary" :to="{ path: '/analytics-aggregates' }" href="#/analytics-aggregates">
                        <v-icon left>mdi-table</v-icon>
                        <?php echo t('Analytics Aggregates'); ?>
                    </v-btn>
                    <v-btn 
                        color="primary" 
                        icon
                        @click="refreshDashboard"
                        :loading="refreshing"
                        :disabled="refreshing">
                        <v-icon>mdi-refresh</v-icon>
                    </v-btn>
                </div>
            </v-col>
        </v-row>

        <dashboard-overview :stats="stats"></dashboard-overview>

        <template v-if="hasAnalytics">
            <analytics-section
                :analytics="safeAnalytics"
                :top-users="analyticsTopUsers"
                :top-pages="analyticsTopPages"
            ></analytics-section>
        </template>
        <template v-else>
            <v-row class="mt-4">
                <v-col cols="12">
                    <v-alert type="info" outlined>
                        <?php echo t('Analytics data is not available. Please ensure tracking is configured.'); ?>
                    </v-alert>
                </v-col>
            </v-row>
        </template>

        <activity-section
            :activity="stats.activity"
            :users="stats.users"
        ></activity-section>

        <system-status-section
            :system="safeSystem"
            :fastapi-status="fastApiStatus"
            :fastapi-jobs="fastApiJobsList"
            :has-fastapi-jobs="hasFastApiJobs"
            :disk-space="stats.disk_space"
            :queue-size="fastApiJobsInfo.queue_size"
        ></system-status-section>

        <api-logs-section
            v-if="hasApiLogs"
            :api-logs="safeApiLogs"
        ></api-logs-section>

        <v-overlay :value="loading" absolute>
            <v-progress-circular 
                indeterminate 
                color="primary" 
                size="64">
            </v-progress-circular>
        </v-overlay>
    </v-container>
</script>

<script type="text/x-template" id="analytics-aggregates-template">
    <v-container class="pa-4">
        <v-row class="mb-4">
            <v-col cols="12" class="d-flex justify-space-between align-center">
                <div>
                    <h1 class="text-h4 font-weight-medium"><?php echo t('Analytics Aggregation'); ?></h1>
                    <p class="text-caption grey--text mt-1">
                        <?php echo t('Manage analytics aggregation jobs and view status.'); ?>
                    </p>
                </div>
                <div class="d-flex align-center">
                    <v-btn class="mr-2" text color="primary" :to="{ path: '/' }" href="#/">
                        <v-icon left>mdi-view-dashboard-outline</v-icon>
                        <?php echo t('Back to Dashboard'); ?>
                    </v-btn>
                    <v-btn class="mr-2" text color="primary" :to="{ path: '/api-log-aggregates' }" href="#/api-log-aggregates">
                        <v-icon left>mdi-traffic-light</v-icon>
                    <?php echo t('API Log Aggregates'); ?>
                    </v-btn>
                    <v-btn color="primary" icon @click="refreshStatus" :loading="loadingStatus" :disabled="loadingStatus">
                        <v-icon>mdi-refresh</v-icon>
                    </v-btn>
                </div>
            </v-col>
        </v-row>

        <v-row>
            <v-col cols="12" md="8">
                <v-card>
                    <v-card-title>
                        <v-icon class="mr-2" color="primary">mdi-calendar-check</v-icon>
                        <?php echo t('Aggregation Status'); ?>
                    </v-card-title>
                    <v-divider></v-divider>
                    <v-card-text>
                        <v-skeleton-loader type="table-heading, text, table-tbody" v-if="loadingStatus"></v-skeleton-loader>
                        <div v-else>
                            <v-alert type="error" dense outlined v-if="statusError">
                                {{ statusError }}
                            </v-alert>
                            <v-simple-table>
                                <tbody>
                                    <tr>
                                        <th class="text-left"><?php echo t('Last Aggregated Date'); ?></th>
                                        <td>{{ statusDisplay.lastAggregated }}</td>
                                    </tr>
                                    <tr>
                                        <th class="text-left"><?php echo t('Total Raw Events'); ?></th>
                                        <td>{{ statusDisplay.totalRawEvents }}</td>
                                    </tr>
                                    <tr>
                                        <th class="text-left"><?php echo t('Aggregated Day Rows'); ?></th>
                                        <td>{{ statusDisplay.aggregatedDays }}</td>
                                    </tr>
                                    <tr>
                                        <th class="text-left"><?php echo t('Events Pending Cleanup'); ?></th>
                                        <td>
                                            <span :class="statusDisplay.oldEvents > 0 ? 'error--text' : ''">
                                                {{ statusDisplay.oldEventsFormatted }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </v-simple-table>
                        </div>
                    </v-card-text>
                </v-card>

                <v-card class="mt-4">
                    <v-card-title>
                        <v-icon class="mr-2" color="info">mdi-play-circle-outline</v-icon>
                        <?php echo t('Run Aggregation'); ?>
                    </v-card-title>
                    <v-divider></v-divider>
                    <v-card-text>
                        <v-alert type="success" dense outlined v-if="successMessage">
                            {{ successMessage }}
                        </v-alert>
                        <v-alert type="error" dense outlined v-if="runError">
                            {{ runError }}
                        </v-alert>

                        <v-form>
                            <v-checkbox
                                v-model="includeCleanup"
                                :label="'<?php echo t('Also run cleanup (archive and purge raw data older than 90 days)'); ?>'">
                            </v-checkbox>
                        </v-form>
                        <div class="d-flex align-center mt-4">
                            <v-btn color="primary" @click="runAggregation" :loading="running" :disabled="running">
                                <v-icon left>mdi-play</v-icon>
                                <?php echo t('Start Aggregation'); ?>
                            </v-btn>
                            <v-spacer></v-spacer>
                            <v-btn text color="primary" :to="{ path: '/' }" href="#/">
                                <v-icon left>mdi-view-dashboard-outline</v-icon>
                                <?php echo t('Back to Dashboard'); ?>
                            </v-btn>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" md="4">
                <v-card>
                    <v-card-title>
                        <v-icon class="mr-2" color="grey">mdi-information-outline</v-icon>
                        <?php echo t('Notes'); ?>
                    </v-card-title>
                    <v-divider></v-divider>
                    <v-card-text>
                        <ul class="pl-4">
                            <li><?php echo t('Aggregation processes raw analytics events into daily summaries for performance.'); ?></li>
                            <li><?php echo t('To keep dashboards accurate, run aggregation regularly (daily via cron recommended).'); ?></li>
                            <li><?php echo t('Cleanup archives raw logs older than 90 days before removing them.'); ?></li>
                            <li><?php echo t('Run via CLI:'); ?> <code style="font-weight:bold;">php index.php api/analytics/aggregate</code></li>
                            <li><?php echo t('use API:'); ?> <code style="font-weight:bold;"><?php echo site_url('api/analytics/aggregate'); ?></code></li>
                        </ul>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</script>

<script type="text/x-template" id="api-logs-aggregates-template">
    <v-container class="pa-4">
        <v-row class="mb-4">
            <v-col cols="12" class="d-flex justify-space-between align-center">
                <div>
                    <h1 class="text-h4 font-weight-medium"><?php echo t('API Logs Aggregation'); ?></h1>
                    <p class="text-caption grey--text mt-1">
                        <?php echo t('Summarize API request logs for dashboard reporting.'); ?>
                    </p>
                </div>
                <div class="d-flex align-center">
                    <v-btn class="mr-2" text color="primary" :to="{ path: '/' }" href="#/">
                        <v-icon left>mdi-view-dashboard-outline</v-icon>
                        <?php echo t('Back to Dashboard'); ?>
                    </v-btn>
                    <v-btn class="mr-2" text color="primary" :to="{ path: '/analytics-aggregates' }" href="#/analytics-aggregates">
                        <v-icon left>mdi-table</v-icon>
                        <?php echo t('Analytics Aggregates'); ?>
                    </v-btn>
                    <v-btn color="primary" icon @click="refreshStatus" :loading="loadingStatus" :disabled="loadingStatus">
                        <v-icon>mdi-refresh</v-icon>
                    </v-btn>
                </div>
            </v-col>
        </v-row>

        <v-row>
            <v-col cols="12" md="8">
                <v-card>
                    <v-card-title>
                        <v-icon class="mr-2" color="primary">mdi-calendar-check</v-icon>
                        <?php echo t('Aggregation Status'); ?>
                    </v-card-title>
                    <v-divider></v-divider>
                    <v-card-text>
                        <v-skeleton-loader type="table-heading, text, table-tbody" v-if="loadingStatus"></v-skeleton-loader>
                        <div v-else>
                            <v-alert type="error" dense outlined v-if="statusError">
                                {{ statusError }}
                            </v-alert>
                            <v-alert type="info" dense outlined v-if="statusData && statusData.available === false">
                                {{ statusData.message || '<?php echo t('API logs tables are unavailable.'); ?>' }}
                            </v-alert>
                            <template v-if="statusData && statusData.available">
                                <v-simple-table>
                                    <tbody>
                                        <tr>
                                            <th class="text-left"><?php echo t('Last Aggregated Date'); ?></th>
                                            <td>{{ statusDisplay.lastAggregated }}</td>
                                        </tr>
                                        <tr>
                                            <th class="text-left"><?php echo t('Total Raw Logs'); ?></th>
                                            <td>{{ statusDisplay.totalRawLogs }}</td>
                                        </tr>
                                        <tr>
                                            <th class="text-left"><?php echo t('Daily Rollup Rows'); ?></th>
                                            <td>{{ statusDisplay.dailyRows }}</td>
                                        </tr>
                                        <tr>
                                            <th class="text-left"><?php echo t('IP Rollup Rows'); ?></th>
                                            <td>{{ statusDisplay.ipRows }}</td>
                                        </tr>
                                        <tr>
                                            <th class="text-left"><?php echo t('User/API Key Rows'); ?></th>
                                            <td>{{ statusDisplay.userRows }}</td>
                                        </tr>
                                    </tbody>
                                </v-simple-table>
                            </template>
                        </div>
                    </v-card-text>
                </v-card>

                <v-card class="mt-4">
                    <v-card-title>
                        <v-icon class="mr-2" color="info">mdi-play-circle-outline</v-icon>
                        <?php echo t('Run API Logs Aggregation'); ?>
                    </v-card-title>
                    <v-divider></v-divider>
                    <v-card-text>
                        <v-alert type="success" dense outlined v-if="successMessage">
                            {{ successMessage }}
                        </v-alert>
                        <v-alert type="error" dense outlined v-if="runError">
                            {{ runError }}
                        </v-alert>

                        <div class="d-flex align-center mt-4">
                            <v-btn color="primary" @click="runAggregation" :loading="running" :disabled="running">
                                <v-icon left>mdi-play</v-icon>
                                <?php echo t('Start Aggregation'); ?>
                            </v-btn>
                            <v-spacer></v-spacer>
                            <v-btn text color="primary" :to="{ path: '/analytics-aggregates' }" href="#/analytics-aggregates">
                                <v-icon left>mdi-table</v-icon>
                                <?php echo t('Analytics Aggregates'); ?>
                            </v-btn>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" md="4">
                <v-card>
                    <v-card-title>
                        <v-icon class="mr-2" color="grey">mdi-information-outline</v-icon>
                        <?php echo t('Notes'); ?>
                    </v-card-title>
                    <v-divider></v-divider>
                    <v-card-text>
                        <ul class="pl-4">
                            <li><?php echo t('Rollups generate daily summaries for API endpoints, IP addresses, and users.'); ?></li>
                            <li><?php echo t('Run regularly to keep dashboard metrics accurate (cron recommended).'); ?></li>
                            <li><?php echo t('Run via CLI:'); ?> <code style="font-weight:bold;">php index.php api/analytics/api_logs_aggregate</code></li>
                            <li><?php echo t('Use API:'); ?> <code style="font-weight:bold;"><?php echo site_url('api/analytics/api_logs_aggregate'); ?></code></li>
                        </ul>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-overlay :value="running" absolute>
            <v-progress-circular indeterminate color="primary" size="64"></v-progress-circular>
        </v-overlay>
    </v-container>
</script>

<div id="dashboard-app" class="dashboard-component">
    <v-app>
        <router-view></router-view>
    </v-app>
</div>

<!-- Vue.js and Vuetify -->
<script src="<?php echo base_url('vue-app/assets/vue.min.js'); ?>"></script>
<script src="<?php echo base_url('vue-app/assets/vue-router.min.js'); ?>"></script>
<script src="<?php echo base_url('vue-app/assets/vuetify.min.js'); ?>"></script>
<script src="<?php echo base_url('vue-app/assets/axios.min.js'); ?>"></script>
<script src="<?php echo base_url('vue-app/assets/chart.min.js'); ?>"></script>
<script>
window.DashboardI18n = {
    PAGE_VIEWS: '<?php echo t('Page Views'); ?>',
    LOAD_TIME: '<?php echo t('Load Time'); ?>',
    SERVER_TIME: '<?php echo t('Server Time'); ?>',
    TIME_MS: '<?php echo t('Time (ms)'); ?>',
    DATE: '<?php echo t('Date'); ?>',
    ANONYMOUS: '<?php echo t('Anonymous'); ?>',
    USER: '<?php echo t('User'); ?>',
    API_KEY: '<?php echo t('API Key'); ?>',
    JUST_NOW: '<?php echo t('Just now'); ?>',
    MINUTES_AGO: '<?php echo t('minutes ago'); ?>',
    HOURS_AGO: '<?php echo t('hours ago'); ?>'
};
</script>
<script src="<?php echo base_url('javascript/dashboard-components.js'); ?>"></script>

<script>
const formattingMixin = {
    methods: {
        formatNumber(value) {
            const number = Number(value || 0);
            if (Number.isNaN(number)) {
                return value;
            }
            return number.toLocaleString();
        },
        formatSeconds(seconds) {
            if (seconds === null || seconds === undefined) {
                return '—';
            }
            const value = Number(seconds);
            if (Number.isNaN(value)) {
                return seconds;
            }
            if (Math.abs(value) < 1) {
                return Math.round(value * 1000) + ' ms';
            }
            return value.toFixed(2) + ' s';
        },
        formatPercent(value, decimals = 1) {
            const number = Number(value || 0);
            return (number * 100).toFixed(decimals) + '%';
        },
        formatIsoDate(value) {
            if (!value) {
                return '';
            }
            try {
                const date = new Date(value);
                if (isNaN(date.getTime())) {
                    return value;
                }
                return date.toLocaleString();
            } catch (e) {
                return value;
            }
        }
    }
};

const DashboardOverview = {
    template: '#dashboard-overview-template',
    props: {
        stats: {
            type: Object,
            required: true
        }
    }
};

const AnalyticsSection = {
    template: '#analytics-section-template',
    mixins: [formattingMixin],
    props: {
        analytics: {
            type: Object,
            required: true
        },
        topUsers: {
            type: Array,
            default: () => []
        },
        topPages: {
            type: Array,
            default: () => []
        }
    },
    data() {
        return {
            trafficChart: null,
            hourlyChart: null
        };
    },
    computed: {
        hasData() {
            return Array.isArray(this.analytics && this.analytics.traffic_chart) && this.analytics.traffic_chart.length > 0;
        },
        slowPages() {
            const performance = this.analytics && this.analytics.performance;
            return performance && Array.isArray(performance.avg_load_times) ? performance.avg_load_times : [];
        }
    },
    watch: {
        analytics: {
            handler() {
                this.refreshCharts();
            },
            deep: true,
            immediate: true
        }
    },
    methods: {
        refreshCharts() {
            this.$nextTick(() => {
                this.updateTrafficChart();
                this.updateHourlyChart();
            });
        },
        destroyCharts() {
            if (this.trafficChart) {
                this.trafficChart.destroy();
                this.trafficChart = null;
            }
            if (this.hourlyChart) {
                this.hourlyChart.destroy();
                this.hourlyChart = null;
            }
        },
        updateTrafficChart() {
            const canvas = this.$refs.trafficChartCanvas;
            const ChartLib = window.Chart;
            if (!canvas || !ChartLib) {
                return;
            }
            if (this.trafficChart) {
                this.trafficChart.destroy();
                this.trafficChart = null;
            }
            const chartData = Array.isArray(this.analytics && this.analytics.traffic_chart) ? this.analytics.traffic_chart : [];
            const today = new Date();
            const labels = [];
            const values = [];
            for (let i = 29; i >= 0; i--) {
                const date = new Date(today);
                date.setDate(date.getDate() - i);
                const isoDate = date.toISOString().split('T')[0];
                const found = chartData.find(item => item.date === isoDate);
                labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
                values.push(found ? parseInt(found.views, 10) : 0);
            }
            const ctx = canvas.getContext('2d');
            this.trafficChart = new ChartLib(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: '<?php echo t('Page Views'); ?>',
                        data: values,
                        borderColor: '#1976D2',
                        backgroundColor: 'rgba(25, 118, 210, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: { size: 14 },
                            bodyFont: { size: 13 }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 },
                            grid: { color: 'rgba(0, 0, 0, 0.05)' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { maxRotation: 45, minRotation: 45 }
                        }
                    }
                }
            });
        },
        updateHourlyChart() {
            const canvas = this.$refs.hourlyChartCanvas;
            const ChartLib = window.Chart;
            if (!canvas || !ChartLib) {
                return;
            }
            if (this.hourlyChart) {
                this.hourlyChart.destroy();
                this.hourlyChart = null;
            }
            const chartData = Array.isArray(this.analytics && this.analytics.hourly_traffic) ? this.analytics.hourly_traffic : [];
            const labels = [];
            const values = [];
            for (let i = 0; i < 24; i++) {
                const found = chartData.find(item => parseInt(item.hour, 10) === i);
                if (i === 0) {
                    labels.push('12 AM');
                } else if (i < 12) {
                    labels.push(i + ' AM');
                } else if (i === 12) {
                    labels.push('12 PM');
                } else {
                    labels.push((i - 12) + ' PM');
                }
                values.push(found ? parseInt(found.views, 10) : 0);
            }
            const ctx = canvas.getContext('2d');
            this.hourlyChart = new ChartLib(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: '<?php echo t('Page Views'); ?>',
                        data: values,
                        borderColor: '#4CAF50',
                        backgroundColor: 'rgba(76, 175, 80, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 2,
                        pointHoverRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 8,
                            titleFont: { size: 12 },
                            bodyFont: { size: 11 }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 },
                            grid: { color: 'rgba(0, 0, 0, 0.05)' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { maxRotation: 45, minRotation: 45, maxTicksLimit: 12 }
                        }
                    }
                }
            });
        },
        formatTime(ms) {
            if (!ms || ms === 0) return '0ms';
            if (ms < 1000) return Math.round(ms) + 'ms';
            const seconds = (ms / 1000).toFixed(1);
            return seconds + 's';
        },
        getPerformanceColor(ms) {
            if (!ms || ms === 0) return 'grey--text';
            if (ms < 1000) return 'success--text';
            if (ms < 3000) return 'warning--text';
            return 'error--text';
        },
        getPerformanceTextColor(ms) {
            if (!ms || ms === 0) return '';
            if (ms < 1000) return 'success';
            if (ms < 3000) return 'warning';
            return 'error';
        }
    },
    beforeDestroy() {
        this.destroyCharts();
    }
};

const ApiLogsSection = {
    template: '#api-logs-section-template',
    mixins: [formattingMixin],
    props: {
        apiLogs: {
            type: Object,
            required: true
        }
    },
    computed: {
        hasData() {
            return !!(this.apiLogs && this.apiLogs.has_data);
        },
        totals() {
            return this.apiLogs && this.apiLogs.totals ? this.apiLogs.totals : {
                total_requests: 0,
                success_count: 0,
                error_count: 0,
                avg_response_time: null,
                error_rate: 0
            };
        },
        topEndpoints() {
            return this.apiLogs && Array.isArray(this.apiLogs.top_endpoints) ? this.apiLogs.top_endpoints : [];
        },
        topIps() {
            return this.apiLogs && Array.isArray(this.apiLogs.top_ips) ? this.apiLogs.top_ips : [];
        },
        topUsers() {
            return this.apiLogs && Array.isArray(this.apiLogs.top_users) ? this.apiLogs.top_users : [];
        }
    },
    methods: {
        getApiUserDisplay(user) {
            if (!user) {
                return '<?php echo t('Anonymous'); ?>';
            }
            if (user.display_name) {
                return user.display_name;
            }
            if (user.username) {
                return user.username;
            }
            if (user.user_id && user.user_id !== 0) {
                return '<?php echo t('User'); ?> #' + user.user_id;
            }
            if (user.api_key) {
                return '<?php echo t('API Key'); ?> ' + user.api_key;
            }
            return '<?php echo t('Anonymous'); ?>';
        }
    }
};

const ActivitySection = {
    template: '#activity-section-template',
    mixins: [formattingMixin],
    props: {
        activity: {
            type: Array,
            default: () => []
        },
        users: {
            type: Object,
            required: true
        }
    },
    computed: {
        inactiveUsersCount() {
            const statuses = Array.isArray(this.users && this.users.by_status) ? this.users.by_status : [];
            const inactive = statuses.find(status => status.status === 'inactive');
            return inactive ? inactive.count : 0;
        }
    },
    methods: {
        formatTimeUnix(timestamp) {
            if (!timestamp) {
                return '';
            }
            const date = new Date(parseInt(timestamp, 10) * 1000);
            const now = new Date();
            const diff = now - date;
            if (diff < 60000) {
                return '<?php echo t('Just now'); ?>';
            } else if (diff < 3600000) {
                const mins = Math.floor(diff / 60000);
                return mins + ' <?php echo t('minutes ago'); ?>';
            } else if (diff < 86400000) {
                const hours = Math.floor(diff / 3600000);
                return hours + ' <?php echo t('hours ago'); ?>';
            }
            return date.toLocaleDateString();
        }
    }
};

const SystemStatusSection = {
    template: '#system-status-section-template',
    mixins: [formattingMixin],
    props: {
        system: {
            type: Object,
            required: true
        },
        fastapiStatus: {
            type: Object,
            required: true
        },
        fastapiJobs: {
            type: Array,
            default: () => []
        },
        hasFastApiJobs: {
            type: Boolean,
            default: false
        },
        diskSpace: {
            type: Object,
            required: true
        },
        queueSize: {
            type: [Number, String],
            default: null
        }
    },
    computed: {
        fastapiJobsList() {
            return Array.isArray(this.fastapiJobs) ? this.fastapiJobs : [];
        },
        diskSpaceColor() {
            if (!this.diskSpace || !this.diskSpace.available) {
                return 'grey';
            }
            switch (this.diskSpace.status) {
                case 'critical':
                    return 'error';
                case 'warning':
                    return 'warning';
                default:
                    return 'success';
            }
        }
    }
};

Vue.component('dashboard-overview', DashboardOverview);
Vue.component('analytics-section', AnalyticsSection);
Vue.component('api-logs-section', ApiLogsSection);
Vue.component('activity-section', ActivitySection);
Vue.component('system-status-section', SystemStatusSection);

const DashboardHome = {
    template: '#dashboard-home-template',
    data() {
        return {
            loading: true,
            refreshing: false,
            lastUpdated: null,
            liveRefreshInterval: null,
            liveRefreshEnabled: true,
            stats: {
                projects: {
                    total: 0,
                    by_type: [],
                    published: 0,
                    unpublished: 0,
                    recent_30_days: 0,
                    this_month: 0
                },
                users: {
                    total: 0,
                    active: 0,
                    this_month: 0,
                    by_status: [],
                    top_active: [],
                    no_projects: 0 // Added for users without projects
                },
                storage: {
                    total_size: 0,
                    total_size_formatted: '0 B',
                    file_count: 0,
                    by_type: [],
                    storage_path: ''
                },
                disk_space: {
                    total: 0,
                    total_formatted: 'N/A',
                    free: 0,
                    free_formatted: 'N/A',
                    used: 0,
                    used_formatted: 'N/A',
                    percentage: 0,
                    status: 'ok',
                    storage_path: '',
                    available: false
                },
                activity: [],
                analytics: null,
                api_logs: null,
                fastapi: null,
                fastapi_jobs: null,
                system: null
            }
        };
    },
    computed: {
        hasAnalytics() {
            return !this.loading && this.stats.analytics !== null && this.stats.analytics !== undefined;
        },
        safeAnalytics() {
            return this.stats.analytics || {
                page_views_today: 0,
                active_users: 0,
                sessions_30d: 0,
                errors_today: 0,
                top_pages: [],
                top_user_agents: [],
                traffic_chart: [],
                hourly_traffic: [],
                top_users: [],
                performance: null
            };
        },
        analyticsTopPages() {
            if (!this.stats || !this.stats.analytics || !this.stats.analytics.top_pages) {
                return [];
            }
            return this.stats.analytics.top_pages;
        },
        analyticsTopUserAgents() {
            if (!this.stats || !this.stats.analytics || !this.stats.analytics.top_user_agents) {
                return [];
            }
            return this.stats.analytics.top_user_agents;
        },
        analyticsTopUsers() {
            if (!this.stats || !this.stats.analytics || !this.stats.analytics.top_users) {
                return [];
            }
            return this.stats.analytics.top_users;
        },
        inactiveUsersCount() {
            if (!this.stats || !this.stats.users || !this.stats.users.by_status) {
                return 0;
            }
            const inactive = this.stats.users.by_status.find(status => status.status === 'inactive');
            return inactive ? inactive.count : 0;
        },
        fastApiStatus() {
            const defaults = {
                status: 'unknown',
                label: '<?php echo t('Unknown'); ?>',
                message: '<?php echo t('FastAPI status is unavailable.'); ?>',
                color: 'grey',
                icon: 'mdi-help-circle',
                base_url: '',
                details: null
            };
            const fastapiData = (this.stats && this.stats.fastapi) ? this.stats.fastapi : {};
            const merged = Object.assign({}, defaults, fastapiData);
            if (merged.details && typeof merged.details !== 'string') {
                try {
                    merged.details = JSON.stringify(merged.details, null, 2);
                } catch (e) {
                    merged.details = String(merged.details);
                }
            }
            return merged;
        },
        fastApiJobsInfo() {
            const empty = {
                queue_size: null,
                active_jobs: []
            };
            if (!this.stats || !this.stats.fastapi_jobs) {
                return empty;
            }
            const payload = this.stats.fastapi_jobs;
            const data = payload && payload.success && payload.data ? payload.data : payload;
            const queue_size = typeof data.queue_size === 'number' ? data.queue_size : null;
            let active_jobs = [];
            if (Array.isArray(data.active_jobs)) {
                active_jobs = data.active_jobs;
            } else if (data.active_jobs && typeof data.active_jobs === 'object') {
                active_jobs = Object.keys(data.active_jobs).map(id => {
                    const jobData = data.active_jobs[id] || {};
                    return Object.assign({ jobid: id }, jobData);
                });
            }
            return {
                queue_size,
                active_jobs
            };
        },
        fastApiJobsList() {
            const jobs = Array.isArray(this.fastApiJobsInfo.active_jobs) ? this.fastApiJobsInfo.active_jobs : [];
            return jobs
                .filter(job => job && typeof job === 'object')
                .map(entry => ({
                    jobid: entry.jobid || '<?php echo t('Unknown'); ?>',
                    jobtype: entry.jobtype || '',
                    status: entry.status || '',
                    created_at: entry.created_at || ''
                }));
        },
        hasFastApiJobs() {
            return this.fastApiJobsList.length > 0;
        },
        safeSystem() {
            return this.stats && this.stats.system ? this.stats.system : {
                php_version: '<?php echo t('Unknown'); ?>',
                os_type: '<?php echo t('Unknown'); ?>',
                memory_limit: '<?php echo t('N/A'); ?>',
                upload_limit: '<?php echo t('N/A'); ?>',
                post_limit: '<?php echo t('N/A'); ?>',
                timezone: '<?php echo t('UTC'); ?>'
            };
        },
        safeApiLogs() {
            return this.stats && this.stats.api_logs ? this.stats.api_logs : {
                available: false,
                has_data: false,
                days: 30,
                totals: {
                    total_requests: 0,
                    success_count: 0,
                    error_count: 0,
                    avg_response_time: null,
                    error_rate: 0
                },
                top_endpoints: [],
                top_ips: [],
                top_users: []
            };
        },
        hasApiLogs() {
            return this.safeApiLogs && this.safeApiLogs.available;
        },
        hasApiLogsData() {
            return this.safeApiLogs && this.safeApiLogs.has_data;
        },
        apiLogsTotals() {
            return this.safeApiLogs && this.safeApiLogs.totals ? this.safeApiLogs.totals : {
                total_requests: 0,
                success_count: 0,
                error_count: 0,
                avg_response_time: null,
                error_rate: 0
            };
        },
        apiLogsTopEndpoints() {
            return this.safeApiLogs && Array.isArray(this.safeApiLogs.top_endpoints) ? this.safeApiLogs.top_endpoints : [];
        },
        apiLogsTopIps() {
            return this.safeApiLogs && Array.isArray(this.safeApiLogs.top_ips) ? this.safeApiLogs.top_ips : [];
        },
        apiLogsTopUsers() {
            return this.safeApiLogs && Array.isArray(this.safeApiLogs.top_users) ? this.safeApiLogs.top_users : [];
        }
    },
    mounted() {
        this.loadDashboardStats();
        this.startLiveRefresh();
    },
    beforeDestroy() {
        this.stopLiveRefresh();
    },
    methods: {
        async loadDashboardStats(refresh = false) {
            try {
                this.loading = true;
                const url = '<?php echo site_url('api/dashboard/stats'); ?>' + (refresh ? '?refresh=1' : '');
                const response = await axios.get(url);
                if (response.data.success) {
                    const incoming = response.data.data || {};
                    this.stats = Object.assign({}, this.stats, incoming);
                    this.updateLastUpdatedTime();
                } else {
                    console.error('Failed to load dashboard stats:', response.data.error);
                }
            } catch (error) {
                console.error('Error loading dashboard stats:', error);
            }
            await this.loadStorageStats(refresh);
            this.loading = false;
        },
        async loadStorageStats(refresh = false) {
            try {
                const url = '<?php echo site_url('api/dashboard/storage'); ?>' + (refresh ? '?refresh=1' : '');
                const response = await axios.get(url);
                if (response.data.success) {
                    this.stats.storage = response.data.data;
                } else {
                    console.error('Failed to load storage stats:', response.data.error);
                }
            } catch (error) {
                console.error('Error loading storage stats:', error);
            }
        },
        async refreshDashboard() {
            try {
                this.refreshing = true;
                await this.loadDashboardStats(true);
            } finally {
                this.refreshing = false;
            }
        },
        updateLastUpdatedTime() {
            const now = new Date();
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const seconds = now.getSeconds().toString().padStart(2, '0');
            this.lastUpdated = `${hours}:${minutes}:${seconds}`;
        },
        startLiveRefresh() {
            if (!this.liveRefreshEnabled) return;
            this.liveRefreshInterval = setInterval(() => {
                this.updateHourlyChartLive();
            }, 60000); // 60 seconds
        },
        
        stopLiveRefresh() {
            if (this.liveRefreshInterval) {
                clearInterval(this.liveRefreshInterval);
                this.liveRefreshInterval = null;
            }
        },
        
        async updateHourlyChartLive() {
            try {
                const url = '<?php echo site_url('api/dashboard/stats'); ?>';
                const response = await axios.get(url);
                if (response.data.success && response.data.data.analytics) {
                    this.stats.analytics = response.data.data.analytics;
                }
            } catch (error) {
                console.error('Error updating hourly chart:', error);
            }
        }
    }
};

const AnalyticsAggregates = {
    template: '#analytics-aggregates-template',
    mixins: [formattingMixin],
    data() {
        return {
            loadingStatus: false,
            statusData: null,
            statusError: null,
            running: false,
            includeCleanup: false,
            successMessage: '',
            runError: ''
        };
    },
    computed: {
        statusDisplay() {
            const data = this.statusData || {};
            const oldEventsRaw = Number(data.old_events_pending_cleanup ?? 0);
            return {
                lastAggregated: data.last_aggregated || '<?php echo t('Never'); ?>',
                totalRawEvents: this.formatNumber(data.total_raw_events ?? 0),
                aggregatedDays: this.formatNumber(data.aggregated_days ?? 0),
                oldEvents: oldEventsRaw,
                oldEventsFormatted: this.formatNumber(oldEventsRaw)
            };
        }
    },
    methods: {
        async refreshStatus() {
            this.loadingStatus = true;
            this.statusError = null;
            try {
                const response = await axios.get('<?php echo site_url('api/analytics/status'); ?>');
                if (response.data.status === 'success') {
                    this.statusData = response.data.data;
                } else {
                    this.statusError = response.data.message || '<?php echo t('Unable to load aggregation status.'); ?>';
                }
            } catch (error) {
                this.statusError = (error.response && error.response.data && error.response.data.message)
                    ? error.response.data.message
                    : (error.message || '<?php echo t('An unexpected error occurred while loading status.'); ?>');
            } finally {
                this.loadingStatus = false;
            }
        },
        buildSuccessMessage(result) {
            if (!result) {
                return '<?php echo t('Aggregation completed successfully.'); ?>';
            }
            const aggregated = result.days_aggregated ?? 0;
            const last = result.last_aggregated ? `<?php echo t('Last aggregated date'); ?>: ${result.last_aggregated}` : '';
            return `${'<?php echo t('Aggregation completed. Days processed'); ?>'}: ${aggregated}${last ? ' • ' + last : ''}`;
        },
        async runAggregation() {
            this.running = true;
            this.successMessage = '';
            this.runError = '';
            try {
                const cleanupParam = this.includeCleanup ? '?cleanup=1' : '';
                const response = await axios.post('<?php echo site_url('api/analytics/aggregate'); ?>' + cleanupParam);
                if (response.data.status === 'success') {
                    this.successMessage = this.buildSuccessMessage(response.data.data);
                    await this.refreshStatus();
                } else {
                    this.runError = response.data.message || '<?php echo t('Aggregation failed to run.'); ?>';
                }
            } catch (error) {
                this.runError = (error.response && error.response.data && error.response.data.message)
                    ? error.response.data.message
                    : (error.message || '<?php echo t('An unexpected error occurred while running aggregation.'); ?>');
            } finally {
                this.running = false;
            }
        }
    },
    mounted() {
        this.refreshStatus();
    }
};

const ApiLogsAggregates = {
    template: '#api-logs-aggregates-template',
    mixins: [formattingMixin],
    data() {
        return {
            loadingStatus: false,
            statusData: null,
            statusError: null,
            running: false,
            successMessage: '',
            runError: ''
        };
    },
    computed: {
        statusDisplay() {
            const data = (this.statusData && this.statusData.available) ? this.statusData : {};
            return {
                lastAggregated: data.last_aggregated || '<?php echo t('Never'); ?>',
                totalRawLogs: this.formatNumber(data.total_raw_logs ?? 0),
                dailyRows: this.formatNumber(data.daily_rows ?? 0),
                ipRows: this.formatNumber(data.ip_rows ?? 0),
                userRows: this.formatNumber(data.user_rows ?? 0)
            };
        }
    },
    methods: {
        async refreshStatus() {
            this.loadingStatus = true;
            this.statusError = null;
            try {
                const response = await axios.get('<?php echo site_url('api/analytics/api_logs_status'); ?>');
                if (response.data.status === 'success') {
                    this.statusData = response.data.data;
                } else {
                    this.statusError = response.data.message || '<?php echo t('Unable to load API logs status.'); ?>';
                }
            } catch (error) {
                this.statusError = (error.response && error.response.data && error.response.data.message)
                    ? error.response.data.message
                    : (error.message || '<?php echo t('An unexpected error occurred while loading status.'); ?>');
            } finally {
                this.loadingStatus = false;
            }
        },
        buildSuccessMessage(result) {
            if (!result) {
                return '<?php echo t('API Logs aggregation completed successfully.'); ?>';
            }
            const aggregated = result.days_aggregated ?? 0;
            const last = result.last_aggregated ? `<?php echo t('Last aggregated date'); ?>: ${result.last_aggregated}` : '';
            return `${'<?php echo t('Aggregation completed. Days processed'); ?>'}: ${aggregated}${last ? ' • ' + last : ''}`;
        },
        async runAggregation() {
            this.running = true;
            this.successMessage = '';
            this.runError = '';
            try {
                const response = await axios.post('<?php echo site_url('api/analytics/api_logs_aggregate'); ?>');
                if (response.data.status === 'success') {
                    this.successMessage = this.buildSuccessMessage(response.data.data);
                    await this.refreshStatus();
                } else {
                    this.runError = response.data.message || '<?php echo t('API Logs aggregation failed to run.'); ?>';
                }
            } catch (error) {
                this.runError = (error.response && error.response.data && error.response.data.message)
                    ? error.response.data.message
                    : (error.message || '<?php echo t('An unexpected error occurred while running API logs aggregation.'); ?>');
            } finally {
                this.running = false;
            }
        }
    },
    mounted() {
        this.refreshStatus();
    }
};

const router = new VueRouter({
    mode: 'hash',
    routes: [
        { path: '/', component: DashboardHome },
        { path: '/analytics-aggregates', component: AnalyticsAggregates },
        { path: '/api-log-aggregates', component: ApiLogsAggregates },
        { path: '*', redirect: '/' }
    ]
});

new Vue({
    el: '#dashboard-app',
    vuetify: new Vuetify({
        theme: {
            themes: {
                light: {
                    primary: '#1976D2',
                    secondary: '#424242',
                    accent: '#82B1FF',
                    error: '#FF5252',
                    warning: '#FB8C00',
                    info: '#2196F3',
                    success: '#4CAF50'
                }
            }
        }
    }),
    router
});
</script>
