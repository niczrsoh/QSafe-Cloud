<?php
/** @var array $_ */
?>
<div id="pqc-recovery-container" style="display:flex; justify-content:center; align-items:center; min-height:100vh; background: #f3f4f6; font-family: 'Segoe UI', sans-serif;">
    <div style="width:100%; max-width:450px; background:white; border-radius:20px; padding:40px; box-shadow:0 10px 30px rgba(0,0,0,0.1);">
        
        <!-- Step 1: Verify Share -->
        <div id="recovery-step-1">
            <div style="text-align:center; margin-bottom:30px;">
                <div style="font-size:40px; margin-bottom:10px;">🔐</div>
                <h2 style="color:#1e293b; margin:0; font-size:24px; font-weight:700;">Account Recovery</h2>
                <p style="color:#64748b; margin-top:5px; font-size:14px;">Use your recovery keys to freeze this account or bind a new Key device.</p>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; color:#475569; font-weight:600; font-size:14px;">Email</label>
                <input type="email" id="recovery-username" placeholder="Enter your registered email" autocomplete="email" style="width:100%; padding:12px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:15px; outline:none; box-sizing:border-box;">
            </div>


            <div style="margin-bottom:20px; padding:15px; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0;">
                <label style="display:block; margin-bottom:8px; color:#475569; font-weight:600; font-size:14px;">Security Questions</label>
                <div id="recovery-kba-question" style="font-size:13px; color:#0369a1; background:white; padding:10px; border-radius:8px; border:1px solid #bae6fd; margin-bottom:10px; min-height:20px;">Enter your email to load your questions.</div>
                <div id="recovery-kba-answers" style="display:flex; flex-direction:column; gap:10px;">
                    <input type="text" id="recovery-kba-answer-1" placeholder="Answer 1" autocomplete="off" style="width:100%; padding:12px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:15px; outline:none; box-sizing:border-box;">
                    <input type="text" id="recovery-kba-answer-2" placeholder="Answer 2" autocomplete="off" style="width:100%; padding:12px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:15px; outline:none; box-sizing:border-box;">
                    <input type="text" id="recovery-kba-answer-3" placeholder="Answer 3" autocomplete="off" style="width:100%; padding:12px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:15px; outline:none; box-sizing:border-box;">
                </div>
                <p style="margin:8px 0 0; font-size:11px; color:#64748b; line-height:1.5;">Answer any 2 correctly with your recovery share. If you forgot most answers, contact admin for assistance.</p>
            </div>

            <div style="margin-bottom:25px;">
                <label style="display:block; margin-bottom:12px; color:#475569; font-weight:600; font-size:14px;">Recovery Option</label>
                
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <div style="padding:15px; border:1.5px solid #e2e8f0; border-radius:12px;">
                        <label style="display:block; margin-bottom:8px; font-size:13px; color:#64748b; font-weight:600;">Option A: Recovery String (Share B)</label>
                        <input type="text" id="recovery-share-b" placeholder="e.g. rtd-673-jdj..." style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-family:monospace;">
                    </div>

                    <div style="text-align:center; color:#94a3b8; font-size:12px; font-weight:700;">— OR —</div>

                    <div style="padding:15px; border:1.5px solid #e2e8f0; border-radius:12px;">
                        <label style="display:block; margin-bottom:8px; font-size:13px; color:#64748b; font-weight:600;">Option B: Recovery File (Share C)</label>
                        <input type="file" id="recovery-share-c" style="font-size:12px;">
                    </div>
                </div>
            </div>

            <button id="verify-recovery-btn" style="width:100%; padding:14px; background:#0f172a; color:white; border:none; border-radius:12px; font-size:16px; font-weight:600; cursor:pointer;">
                Verify & Continue
            </button>
        </div>

        <!-- Step 2: Recovery Action (Hidden) -->
        <div id="recovery-step-2" style="display:none;">
            <div style="text-align:center; margin-bottom:30px;">
                <div style="font-size:40px; margin-bottom:10px;">🔐</div>
                <h2 style="color:#1e293b; margin:0; font-size:24px; font-weight:700;">Recovery Verified</h2>
                <p style="color:#64748b; margin-top:5px; font-size:14px;">Choose how you want to protect this account.</p>
            </div>

            <div style="margin-bottom:22px; padding:20px; background:#fff7ed; border-radius:12px; border:1px solid #fed7aa; text-align:center;">
                <div style="font-size:13px; color:#9a3412; font-weight:700; margin-bottom:8px;">Freeze this account</div>
                <p style="margin:0 0 14px; font-size:12px; line-height:1.5; color:#7c2d12;">Use this if your Key or laptop may be stolen. The account will be disabled until an admin unlocks it.</p>
                <button type="button" id="freeze-account-btn" style="width:100%; padding:12px; background:#ea580c; color:white; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer;">
                    Freeze Account
                </button>
            </div>

            <div style="display:flex; align-items:center; gap:12px; margin:20px 0; color:#94a3b8; font-size:12px; font-weight:700; text-transform:uppercase;">
                <div style="height:1px; background:#e2e8f0; flex:1;"></div>
                <span>Or</span>
                <div style="height:1px; background:#e2e8f0; flex:1;"></div>
            </div>

            <div style="margin-bottom:25px; padding:20px; background:#f8fafc; border-radius:12px; border:1px dashed #38bdf8; text-align:center;">
                <div style="font-size:13px; color:#0369a1; font-weight:700; margin-bottom:8px;">Bind a new Key</div>
                <p style="margin:0 0 14px; font-size:12px; line-height:1.5; color:#475569;">Use this if you have a replacement device ready.</p>
                <div style="display:flex; gap:10px; margin-bottom:12px;">
                    <label style="flex:1; display:flex; align-items:center; justify-content:center; gap:8px; padding:10px 12px; border:1px solid #bae6fd; border-radius:10px; background:white; cursor:pointer; font-size:13px; color:#0369a1; font-weight:700;">
                        <input type="radio" name="rebind-slot" value="primary" checked>
                        Primary
                    </label>
                    <label style="flex:1; display:flex; align-items:center; justify-content:center; gap:8px; padding:10px 12px; border:1px solid #e2e8f0; border-radius:10px; background:white; cursor:pointer; font-size:13px; color:#475569; font-weight:700;">
                        <input type="radio" name="rebind-slot" value="backup">
                        secondary
                    </label>
                </div>
                <button type="button" id="rebind-pqc-btn" style="width:100%; padding:12px; background:#38bdf8; color:white; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer;">
                    🔌 Bind New Key
                </button>
                <div id="pqc-key-display" style="display:none; margin-top:15px;">
                    <div id="pqc-key-value" style="font-family:monospace; font-size:11px; word-break:break-all; color:#0369a1; background:white; padding:10px; border-radius:8px; border:1px solid #bae6fd; font-weight:bold;"></div>
                </div>
                <p id="pqc-status-msg" style="margin-top:10px; font-size:11px; color:#64748b;">Connect via USB and click Bind.</p>
            </div>

            <button id="final-rebind-btn" disabled style="width:100%; padding:14px; background:#94a3b8; color:white; border:none; border-radius:12px; font-size:16px; font-weight:600; cursor:not-allowed;">
                Complete Rebinding
            </button>
        </div>

        <!-- Step 3: New SSS (Hidden) -->
        <div id="recovery-step-3" style="display:none;">
            <div style="text-align:center; margin-bottom:25px;">
                <div style="font-size:40px; margin-bottom:10px;">🛡️</div>
                <h2 style="color:#1e293b; margin:0; font-size:22px; font-weight:700;">New Recovery Keys</h2>
                <p style="color:#64748b; margin-top:5px; font-size:13px;">Old keys are now invalid. Save these new ones.</p>
            </div>

            <div style="margin-bottom:20px; padding:15px; background:#f0f9ff; border-radius:12px;">
                <div id="new-sss-share-b" style="font-family:monospace; font-size:14px; background:white; padding:10px; border-radius:8px; border:1px solid #bae6fd; color:#0369a1; font-weight:bold; margin-bottom:8px;"></div>
                <button id="copy-new-share-b" style="width:100%; padding:8px; background:#38bdf8; color:white; border:none; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer;">Copy String</button>
            </div>

            <button id="download-new-share-c" style="width:100%; padding:12px; background:#0f172a; color:white; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; margin-bottom:20px;">
                📥 Download new recovery.pqc
            </button>

            <a href="/index.php/login" style="display:block; text-align:center; text-decoration:none; width:100%; padding:14px; background:#22c55e; color:white; border:none; border-radius:12px; font-size:16px; font-weight:600; box-sizing:border-box;">
                Return to Login
            </a>
        </div>

        <!-- Step 4: Account Frozen (Hidden) -->
        <div id="recovery-step-freeze" style="display:none;">
            <div style="text-align:center; margin-bottom:25px;">
                <div style="font-size:40px; margin-bottom:10px;">🧊</div>
                <h2 style="color:#1e293b; margin:0; font-size:22px; font-weight:700;">Account Frozen</h2>
                <p style="color:#64748b; margin-top:5px; font-size:13px; line-height:1.5;">Your account has been disabled for protection. Contact an administrator when you are ready to unlock or recover it.</p>
            </div>

            <a href="/index.php/login" style="display:block; text-align:center; text-decoration:none; width:100%; padding:14px; background:#0f172a; color:white; border:none; border-radius:12px; font-size:16px; font-weight:600; box-sizing:border-box;">
                Return to Login
            </a>
        </div>

        <div style="text-align:center; margin-top:20px;">
            <a href="/index.php/login" style="color:#64748b; font-size:14px; text-decoration:none;">Cancel</a>
        </div>
    </div>
</div>
